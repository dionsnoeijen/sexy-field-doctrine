<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Configuration;
use Mockery as m;
use Doctrine\ORM;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Tardigrades\SectionField\Service\FetchFieldsDoctrineSectionReader
 * @covers ::__construct
 * @covers ::buildQuery
 * @covers ::<private>
 */
final class FetchFieldsDoctrineSectionReaderTest extends TestCase
{
    use m\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /** @var ORM\Entitymanager|m\Mock */
    private $entityManager;

    /** @var FetchFieldsDoctrineSectionReader */
    private $reader;

    /** @var Registry|m\MockInterface */
    private $registry;

    protected function setUp(): void
    {
        $this->entityManager = m::mock(ORM\EntityManager::class)->makePartial();
        $this->registry = m::mock(Registry::class);
        $this->reader = new FetchFieldsDoctrineSectionReader($this->registry);
    }

    public function testOneToManyJoins(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => \TestNS\Product::class,
            ReadOptions::FETCH_FIELDS => 'slug,price,currency,product',
            ReadOptions::LIMIT => 5,
            ReadOptions::ORDER_BY => ['prices:price' => 'ASC']
        ]);

        $this->givenWeHaveAValidEntityAssignedToAManager(\TestNS\Product::class);

        $expected = static::normalize(<<<'DQL'
            SELECT
              product.productSlug AS product:productSlug,
              product:prices.priceSlug AS product:prices:priceSlug,
              product:prices.price AS product:prices:price,
              product:prices.currency AS product:prices:currency
            FROM TestNS\Product product
            LEFT JOIN product.prices product:prices
            ORDER BY product:prices:price ASC
DQL
        );

        $builder = $this->reader->buildQuery($readOptions);
        $this->assertSame($expected, $builder->getDQL());
        $this->assertSame(5, $builder->getMaxResults());
    }

    public function testManyToOneJoins(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => \TestNS\Price::class,
            ReadOptions::FETCH_FIELDS => 'slug,product,name'
        ]);

        $this->givenWeHaveAValidEntityAssignedToAManager(\TestNS\Price::class);

        $expected = static::normalize(<<<'DQL'
            SELECT
              price.priceSlug AS price:priceSlug,
              price:product.productSlug AS price:product:productSlug,
              price:product.name AS price:product:name
            FROM TestNS\Price price
            LEFT JOIN price.product price:product
DQL
        );

        $builder = $this->reader->buildQuery($readOptions);
        $this->assertSame($expected, $builder->getDQL());
    }

    public function testMultipleFieldValues(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => \TestNS\Product::class,
            ReadOptions::FIELD => ['name' => ['Space Frikandel', 'Ongewokkel']],
            ReadOptions::FETCH_FIELDS => 'name,slug'
        ]);

        $this->givenWeHaveAValidEntityAssignedToAManager(\TestNS\Product::class);

        $expected = static::normalize(<<< DQL
            SELECT
              product.name AS product:name,
              product.productSlug AS product:productSlug
            FROM TestNS\Product product
            WHERE product.name IN (?1)
DQL
        );

        $builder = $this->reader->buildQuery($readOptions);
        $this->assertSame($expected, $builder->getDQL());
        $this->assertSame(['Space Frikandel', 'Ongewokkel'], $builder->getParameter(1)->getValue());
    }

    public function testSlugFetch(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => \TestNS\Product::class,
            ReadOptions::SLUG => 'ikbeneenproduct',
            ReadOptions::FETCH_FIELDS => 'name'
        ]);

        $this->givenWeHaveAValidEntityAssignedToAManager(\TestNS\Product::class);

        $expected = static::normalize(<<< DQL
            SELECT
              product.name AS product:name
            FROM TestNS\Product product
            WHERE product.productSlug = (?1)
DQL
        );

        $builder = $this->reader->buildQuery($readOptions);
        $this->assertSame($expected, $builder->getDQL());
        $this->assertSame('ikbeneenproduct', $builder->getParameter(1)->getValue());
    }

    /**
     * @test
     */
    public function testThatItFailsOnASluglessClass(): void
    {
        $this->expectExceptionMessage("Class doesn't have a slug field");
        $this->expectException(InvalidFetchFieldsQueryException::class);

        $this->givenWeHaveAValidEntityAssignedToAManager(\TestNS\Slugless::class);

        $this->reader->buildQuery(
            ReadOptions::fromArray([
                ReadOptions::SECTION => \TestNS\Slugless::class,
                ReadOptions::FETCH_FIELDS => 'slug'
            ])
        );
    }

    public function testThatItFailsWhenNotSelectingFields(): void
    {
        $this->expectExceptionMessage("Not selecting any fields");
        $this->expectException(InvalidFetchFieldsQueryException::class);

        $this->givenWeHaveAValidEntityAssignedToAManager(\TestNS\Product::class);

        $this->reader->buildQuery(
            ReadOptions::fromArray([
                ReadOptions::SECTION => \TestNS\Product::class,
                ReadOptions::FETCH_FIELDS => ''
            ])
        );
    }

    /**
     * @test
     */
    public function testThatItFailsWhenSelectingOnlyInvalidFields(): void
    {
        $this->expectException(\Tardigrades\SectionField\Service\InvalidFetchFieldsQueryException::class);
        $this->expectExceptionMessage("Could not find any of the fields");
        $this->givenWeHaveAValidEntityAssignedToAManager(\TestNS\Product::class);

        $this->reader->buildQuery(
            ReadOptions::fromArray([
                ReadOptions::SECTION => \TestNS\Product::class,
                ReadOptions::FETCH_FIELDS => 'not,real,fields'
            ])
        );
    }

    /** @covers ::makeNested */
    public function testNesting(): void
    {
        $this->assertSame(
            [
                'foo' => [
                    'bar' => 3,
                    'baz' => [
                        'qux' => 'foobar',
                        'bar' => 'foo'
                    ]
                ],
                'bar' => 10
            ],
            $this->reader::makeNested([
                'foo:bar' => 3,
                'foo:baz:qux' => 'foobar',
                'foo:baz:bar' => 'foo',
                'bar' => 10
            ])
        );
    }

    private static function normalize(string $query): string
    {
        return trim(preg_replace('/\\s\\s+/', ' ', $query));
    }


    private function givenWeHaveAValidEntityAssignedToAManager(string $namespace)
    {
        $configuration = m::mock(Configuration::class);
        $configuration->shouldReceive('getEntityNamespaces')
            ->once()
            ->andReturn([$namespace]);

        $this->entityManager->shouldReceive('getConfiguration')
            ->once()
            ->andReturn($configuration);

        $this->registry->shouldReceive('getManagers')
            ->once()
            ->andReturn([
                $this->entityManager
            ]);
    }
}

namespace TestNS;

class Product
{
    public static function fieldInfo(): array
    {
        return [
            'productSlug' => [
                'handle' => 'productSlug',
                'type' => 'Slug',
                'getter' => 'getProductSlug',
                'setter' => 'setProductSlug',
                'relationship' => null
            ],
            'name' => [
                'handle' => 'name',
                'type' => 'TextInput',
                'getter' => 'getName',
                'setter' => 'setName',
                'relationship' => null
            ],
            'prices' => [
                'handle' => 'oneProductToManyPrice',
                'type' => 'Relationship',
                'getter' => 'getPrices',
                'setter' => 'addPrice',
                'relationship' => [
                    'class' => 'TestNS\\Price',
                    'plural' => true,
                    'kind' => 'one-to-many',
                    'owner' => false
                ]
            ],
            'categories' => [
                'handle' => 'manyProductToManyCategory',
                'type' => 'Relationship',
                'getter' => 'getProductCategories',
                'setter' => 'addProductCategory',
                'relationship' => [
                    'class' => 'TestNS\\ProductCategory',
                    'plural' => true,
                    'kind' => 'many-to-many',
                    'owner' => true
                ],
            ],
            'status' => [
                'handle' => 'manyProductToOneStatus',
                'type' => 'Relationship',
                'getter' => 'getStatus',
                'setter' => 'setStatus',
                'relationship' => [
                    'class' => 'TestNS\\Status',
                    'plural' => false,
                    'kind' => 'many-to-one',
                    'owner' => true
                ],
            ]
        ];
    }
}

class Price
{
    public static function fieldInfo(): array
    {
        return [
            'priceSlug' => [
                'handle' => 'priceSlug',
                'type' => 'Slug',
                'getter' => 'getPriceSlug',
                'setter' => 'setPriceSlug',
                'relationship' => null,
            ],
            'price' => [
                'handle' => 'price',
                'type' => 'TextInput',
                'getter' => 'getPrice',
                'setter' => 'setPrice',
                'relationship' => null
            ],
            'currency' => [
                'handle' => 'currency',
                'type' => 'TextInput',
                'getter' => 'getCurrency',
                'setter' => 'setCurrency',
                'relationship' => null
            ],
            'product' => [
                'handle' => 'manyPriceToOneProduct',
                'type' => 'Relationship',
                'getter' => 'getProduct',
                'setter' => 'setProduct',
                'relationship' => [
                    'class' => 'TestNS\\Product',
                    'plural' => false,
                    'kind' => 'many-to-one',
                    'owner' => true
                ]
            ]
        ];
    }
}

class ProductCategory
{
    public static function fieldInfo(): array
    {
        return [
            'productCategorySlug' => [
                'handle' => 'productCategorySlug',
                'type' => 'Slug',
                'getter' => 'getProductCategorySlug',
                'setter' => 'setProductCategorySlug',
                'relationship' => null
            ],
            'name' => [
                'handle' => 'name',
                'type' => 'TextInput',
                'getter' => 'getName',
                'setter' => 'setName',
                'relationship' => null
            ],
        ];
    }
}

class Status
{
    public static function fieldInfo(): array
    {
        return [
            'statusSlug' => [
                'handle' => 'statusSlug',
                'type' => 'Slug',
                'getter' => 'getStatusSlug',
                'setter' => 'setStatusSlug',
                'relationship' => null
            ],
            'value' => [
                'handle' => 'value',
                'type' => 'TextInput',
                'getter' => 'getValue',
                'setter' => 'setValue',
                'relationship' => null
            ]
        ];
    }
}

class Slugless
{
    public static function fieldInfo(): array
    {
        return [];
    }
}
