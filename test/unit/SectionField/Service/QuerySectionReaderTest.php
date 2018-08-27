<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use PHPUnit\Framework\TestCase;
use Mockery;
use Tardigrades\SectionField\QueryComponents\QueryStructure;
use Tardigrades\SectionField\QueryComponents\TransformResultsInterface;
use Tardigrades\SectionField\ValueObject\SectionConfig;
use Tardigrades\SectionField\ValueObject\Sort;

/**
 * @coversDefaultClass Tardigrades\SectionField\Service\QuerySectionReader
 * @covers ::<private>
 */
final class QuerySectionReaderTest extends TestCase
{
    /** @var QuerySectionReader */
    private $querySectionReader;

    /** @var EntityManager|Mockery\Mock */
    private $entityManager;

    /** @var Expr */
    private $expressionBuilder;

    /** @var QueryBuilder */
    private $queryBuilder;

    /** @var TransformResultsInterface|Mockery\MockInterface */
    private $transform;

    use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public function setUp(): void
    {
        $this->entityManager = Mockery::mock(EntityManager::class);
        $queryStructure = new QueryStructure();
        $this->queryBuilder = new QueryBuilder($this->entityManager);
        $this->expressionBuilder = new Expr();
        $this->transform = Mockery::mock(TransformResultsInterface::class);

        $this->querySectionReader = new QuerySectionReader(
            $this->entityManager,
            $queryStructure,
            $this->transform,
            $this->queryBuilder
        );
    }

    /**
     * @test
     */
    public function it_should_add_from_to_query(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product'
        ]);

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());
        $this->assertEquals(
            'SELECT product.* FROM This\Is\A\Product product',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * OK, Doctrine doesn't add the limit and or offset to the query... ?
     *
     * @test
     */
    public function it_should_add_a_limit_and_offset_to_query():void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::LIMIT => 100,
            ReadOptions::OFFSET => 10
        ]);

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());
        $this->assertEquals(100, $this->queryBuilder->getMaxResults());
        $this->assertEquals(10, $this->queryBuilder->getFirstResult());
        $this->assertEquals(
            'SELECT product.* FROM This\Is\A\Product product',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_order_by_to_query():void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::ORDER_BY => [ 'product.name' => Sort::ASC ]
        ]);

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());
        $this->assertEquals(
            'SELECT product.* FROM This\Is\A\Product product',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_select_slug_by_fetch_fields_to_query(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::FETCH_FIELDS => 'slug'
        ]);

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());
        $this->assertEquals(
            'SELECT product.productSlug AS product_productSlug FROM This\Is\A\Product product',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_join_one_to_many_to_query(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::FETCH_FIELDS => 'slug,prices,price,currency'
        ]);

        $expected = <<<'DQL'
            SELECT
              prices.price AS prices_price,
              prices.currency AS prices_currency,
              product.productSlug AS product_productSlug,
              price.priceSlug AS price_priceSlug
            FROM This\Is\A\Product product
            LEFT JOIN This\Is\A\Price prices WITH prices = product.prices
DQL;

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());
        $this->assertEquals(
            trim(preg_replace('/\s\s+/', ' ', $expected)),
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_join_many_to_many_to_query(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::FETCH_FIELDS => 'slug,name,categories'
        ]);

        $expected = <<<'DQL'
            SELECT
              product.name AS product_name,
              categories.name AS categories_name,
              product.productSlug AS product_productSlug,
              productCategory.productCategorySlug AS productCategory_productCategorySlug
            FROM This\Is\A\Product product
            INNER JOIN product.categories productCategory
DQL;

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());
        $this->assertEquals(
            trim(preg_replace('/\s\s+/', ' ', $expected)),
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_where_id_to_main_section(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::ID => 10
        ]);

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());

        $this->assertEquals(
            'SELECT product.* FROM This\Is\A\Product product WHERE id = :id',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_where_slug_to_main_section(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::SLUG => 'product-slug'
        ]);

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());

        $this->assertEquals(
            'SELECT product.* FROM This\Is\A\Product product WHERE slug = :slug',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_where_by_field_value(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::FIELD => [ 'name' => 'Space Frikandel' ]
        ]);

        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());

        $this->assertEquals(
            'SELECT product.* FROM This\Is\A\Product product WHERE name = :field',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_where_by_multiple_field_values(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'This\Is\A\Product',
            ReadOptions::FIELD => ['name' => ['Space Frikandel', 'Ongewokkel']]
        ]);

        $this->entityManager->shouldReceive('getExpressionBuilder')->once()->andReturn($this->expressionBuilder);
        $this->querySectionReader->read($readOptions, $this->givenASectionConfig());

        $this->assertEquals(
            'SELECT product.* FROM This\Is\A\Product product WHERE Product.name IN(:fields)',
            $this->queryBuilder->getDQL()
        );
    }

    /**
     * @test
     */
    public function it_should_add_a_select_from_one_section_with_where_on_foreign_section()
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'AppBundle\Entity\ParticipantSession',
            ReadOptions::FIELD => [ 'project:product:slug' => [
                '2a5e45ab-7912-4ff7-81f0-89ca574b6a27',
                'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'fd47afed-9ee0-4f7a-9bab-9e5504b5c5aa'
            ]],
            ReadOptions::FETCH_FIELDS => 'uuid,participantSessionAppointmentDate'
        ]);

        $this->entityManager->shouldReceive('getExpressionBuilder')->once()->andReturn($this->expressionBuilder);
        $this->querySectionReader->read($readOptions, $this->givenASessionSectionConfig());
        $dql = $this->queryBuilder->getDQL();

        $expected = <<<'DQL'
            SELECT 
              participantSession.participantSessionAppointmentDate AS participantSession_participantSessionAppointmentDate, 
              participantSession.uuid AS participantSession_uuid, 
              project.uuid AS project_uuid, 
              product.uuid AS product_uuid 
            FROM AppBundle\Entity\ParticipantSession participantSession 
            LEFT JOIN AppBundle\Entity\Project project WITH project = participantSession.project 
            LEFT JOIN AppBundle\Entity\Product product WITH product = project.product 
            WHERE product.productSlug IN(:fields)
DQL;

        $this->assertSame(
            trim(preg_replace('/\s\s+/', ' ', $expected)),
            $dql
        );
    }

    /**
     * @test
     */
    public function it_should_join_aliassed_relationships_combined_with_the_same_none_aliassed_relationship()
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'AppBundle\Entity\ParticipantSession',
            ReadOptions::FIELD => [ 'project:product:slug' => [
                '2a5e45ab-7912-4ff7-81f0-89ca574b6a27',
                'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'fd47afed-9ee0-4f7a-9bab-9e5504b5c5aa'
            ]],
            ReadOptions::FETCH_FIELDS => 'uuid,accountHasRole,account,consultant,firstName,infix,lastName'
        ]);

        $this->entityManager->shouldReceive('getExpressionBuilder')->once()->andReturn($this->expressionBuilder);
        $this->querySectionReader->read($readOptions, $this->givenASessionSectionConfig());
        $dql = $this->queryBuilder->getDQL();

        $expected = <<<'DQL'
            SELECT 
              participantSession.uuid AS participantSession_uuid, 
              consultant.uuid AS consultant_uuid, 
              accountHasRole.uuid AS accountHasRole_uuid, 
              project.uuid AS project_uuid, 
              product.uuid AS product_uuid, 
              consultant_account.firstName AS consultant_account_firstName, 
              consultant_account.infix AS consultant_account_infix, 
              consultant_account.lastName AS consultant_account_lastName, 
              consultant_account.uuid AS consultant_account_uuid, 
              accountHasRole_account.firstName AS accountHasRole_account_firstName, 
              accountHasRole_account.infix AS accountHasRole_account_infix, 
              accountHasRole_account.lastName AS accountHasRole_account_lastName, 
              accountHasRole_account.uuid AS accountHasRole_account_uuid, 
              participantSession.participantSessionSlug AS participantSession_participantSessionSlug, 
              accountHasRole.accountHasRoleSlug AS accountHasRole_accountHasRoleSlug, 
              project.projectSlug AS project_projectSlug, 
              product.productSlug AS product_productSlug, 
              consultant_account.accountSlug AS consultant_account_accountSlug, 
              accountHasRole_account.accountSlug AS accountHasRole_account_accountSlug 
            FROM AppBundle\Entity\ParticipantSession participantSession 
            LEFT JOIN AppBundle\Entity\AccountHasRole consultant WITH consultant = participantSession.consultant 
            LEFT JOIN AppBundle\Entity\AccountHasRole accountHasRole WITH accountHasRole = participantSession.accountHasRole 
            LEFT JOIN AppBundle\Entity\Project project WITH project = participantSession.project 
            LEFT JOIN AppBundle\Entity\Product product WITH product = project.product 
            LEFT JOIN AppBundle\Entity\Account consultant_account WITH consultant_account = consultant 
            LEFT JOIN AppBundle\Entity\Account accountHasRole_account WITH accountHasRole_account = accountHasRole 
            WHERE product.productSlug IN(:fields)
DQL;

        $this->assertSame(
            trim(preg_replace('/\s\s+/', ' ', $expected)),
            $dql
        );
    }

    /**
     * @test
     */
    public function it_should_make_different_types_of_joins_and_add_extra_data(): void
    {
        $readOptions = ReadOptions::fromArray([
            ReadOptions::SECTION => 'AppBundle\Entity\ParticipantSession',
            ReadOptions::FIELD => [ 'project:product:slug' => [
                '2a5e45ab-7912-4ff7-81f0-89ca574b6a27',
                'bab11893-045b-4c26-9cc5-586d879ae4ac',
                'fd47afed-9ee0-4f7a-9bab-9e5504b5c5aa'
            ]],
            ReadOptions::FETCH_FIELDS => 'uuid,participantSessionAppointmentDate,accountHasRole,account,firstName,infix,lastName,consultant,project,organisation,organisationName,organisationType'
        ]);

        $this->entityManager->shouldReceive('getExpressionBuilder')->once()->andReturn($this->expressionBuilder);
        $this->querySectionReader->read($readOptions, $this->givenASessionSectionConfig());

        $expected = <<<'DQL'
            SELECT 
                participantSession.participantSessionAppointmentDate AS participantSession_participantSessionAppointmentDate,
                participantSession.participantSessionAppointmentDateTimezone AS participantSession_participantSessionAppointmentDateTimezone,
                participantSession.uuid AS participantSession_uuid, 
                consultant.uuid AS consultant_uuid, 
                accountHasRole.uuid AS accountHasRole_uuid, 
                project.uuid AS project_uuid, 
                organisation.organisationName AS organisation_organisationName, 
                organisation.organisationType AS organisation_organisationType, 
                organisation.uuid AS organisation_uuid, 
                product.uuid AS product_uuid, 
                consultant_account.firstName AS consultant_account_firstName, 
                consultant_account.infix AS consultant_account_infix, 
                consultant_account.lastName AS consultant_account_lastName, 
                consultant_account.uuid AS consultant_account_uuid, 
                accountHasRole_account.firstName AS accountHasRole_account_firstName, 
                accountHasRole_account.infix AS accountHasRole_account_infix, 
                accountHasRole_account.lastName AS accountHasRole_account_lastName, 
                accountHasRole_account.uuid AS accountHasRole_account_uuid, 
                participantSession.participantSessionSlug AS participantSession_participantSessionSlug, 
                accountHasRole.accountHasRoleSlug AS accountHasRole_accountHasRoleSlug, 
                project.projectSlug AS project_projectSlug, 
                organisation.organisationSlug AS organisation_organisationSlug, 
                product.productSlug AS product_productSlug, 
                consultant_account.accountSlug AS consultant_account_accountSlug, 
                accountHasRole_account.accountSlug AS accountHasRole_account_accountSlug 
            FROM AppBundle\Entity\ParticipantSession participantSession 
            LEFT JOIN AppBundle\Entity\AccountHasRole consultant WITH consultant = participantSession.consultant 
            LEFT JOIN AppBundle\Entity\AccountHasRole accountHasRole WITH accountHasRole = participantSession.accountHasRole 
            LEFT JOIN AppBundle\Entity\Project project WITH project = participantSession.project 
            LEFT JOIN AppBundle\Entity\Organisation organisation WITH organisation = project.organisation 
            LEFT JOIN AppBundle\Entity\Product product WITH product = project.product 
            LEFT JOIN AppBundle\Entity\Account consultant_account WITH consultant_account = consultant 
            LEFT JOIN AppBundle\Entity\Account accountHasRole_account WITH accountHasRole_account = accountHasRole 
            WHERE product.productSlug IN(:fields)
DQL;

        $this->assertSame(
            trim(preg_replace('/\s\s+/', ' ', $expected)),
            $this->queryBuilder->getDQL()
        );
    }

    private function givenASessionSectionConfig(): SectionConfig
    {
        return SectionConfig::fromArray([
            'section' => [
                'name' => 'Session',
                'handle' => 'session',
                'fields' => [
                    'title' => [],
                    'sessionSlug' => []
                ],
                'default' => 'title',
                'slug' => 'sessionSlug',
                'namespace' => 'AppBundle\Entity\Session'
            ]
        ]);
    }

    private function givenASectionConfig(): SectionConfig
    {
        return SectionConfig::fromArray([
            'section' => [
                'name' => 'Product',
                'handle' => 'product',
                'fields' => [
                    'title' => [],
                    'productSlug' => []
                ],
                'default' => 'title',
                'slug' => 'productSlug',
                'namespace' => 'This\Is\A\Product'
            ]
        ]);
    }
}

namespace AppBundle\Entity;

class ParticipantSession {

    // uuid, <- session,project,product
    // sessionAppointmentDate, <- session
    // accountRole, <- session -> oneSessionToOneaccountRole (oneToOne)
    // consultant, <- session -> consultant (manyToOne)
    // project, <- session -> manySessionToOneProject (manyToOne)

    public static function getFields() {
        return [
            'consultant' => [
                'handle' => 'consultant',
                'type' => 'Relationship',
                'getter' => 'getConsultant',
                'setter' => 'setConsultant',
                'relationship' => [
                    'class' => 'AppBundle\\Entity\\AccountHasRole',
                    'plural' => false,
                    'kind' => 'many-to-one',
                ],
            ],
            'accountHasRole' => [
                'handle' => 'oneSessionToOneAccountHasRole',
                'type' => 'Relationship',
                'getter' => 'getAccountHasRole',
                'setter' => 'setAccountHasRole',
                'relationship' => [
                    'class' => 'AppBundle\\Entity\\AccountHasRole',
                    'plural' => false,
                    'kind' => 'one-to-one',
                ],
            ],
            'created' => [
                'handle' => 'created',
                'type' => 'DateTimeField',
                'getter' => 'getCreated',
                'setter' => 'setCreated',
                'relationship' => null,
            ],
            'project' => [
                'handle' => 'manyParticipantSessionToOneProject',
                'type' => 'Relationship',
                'getter' => 'getProject',
                'setter' => 'setProject',
                'relationship' => [
                    'class' => 'AppBundle\\Entity\\Project',
                    'plural' => false,
                    'kind' => 'many-to-one',
                ],
            ],
            'participantSessionAppointmentDate' => [
                'handle' => 'participantSessionAppointmentDate',
                'type' => 'DateTimeTimezone',
                'getter' => 'getParticipantSessionAppointmentDate',
                'setter' => 'setParticipantSessionAppointmentDate',
                'relationship' => null,
            ],
            'participantSessionSlug' => [
                'handle' => 'participantSessionSlug',
                'type' => 'Slug',
                'getter' => 'getParticipantSessionSlug',
                'setter' => 'setParticipantSessionSlug',
                'relationship' => null,
            ],
            'updated' => [
                'handle' => 'updated',
                'type' => 'DateTimeField',
                'getter' => 'getUpdated',
                'setter' => 'setUpdated',
                'relationship' => null,
            ],
            'uuid' => [
                'handle' => 'uuid',
                'type' => 'Uuid',
                'getter' => 'getUuid',
                'setter' => 'setUuid',
                'relationship' => null,
            ]
        ];
    }
}

namespace AppBundle\Entity;

class Product
{
    public static function getFields() {
        return [
            'created' => [
                'handle' => 'created',
                'type' => 'DateTimeField',
                'getter' => 'getCreated',
                'setter' => 'setCreated',
                'relationship' => null,
            ],
            'productSlug' => [
                'handle' => 'productSlug',
                'type' => 'Slug',
                'getter' => 'getProductSlug',
                'setter' => 'setProductSlug',
                'relationship' => null,
            ],
            'updated' => [
                'handle' => 'updated',
                'type' => 'DateTimeField',
                'getter' => 'getUpdated',
                'setter' => 'setUpdated',
                'relationship' => null,
            ],
            'uuid' => [
                'handle' => 'uuid',
                'type' => 'Uuid',
                'getter' => 'getUuid',
                'setter' => 'setUuid',
                'relationship' => null,
            ]
        ];
    }
}

namespace AppBundle\Entity;

class Project {
    // organisation, <- project -> manyProjectToOneOrganisation (manyToOne)

    public static function getFields()
    {
        return [
            'created' => [
                'handle' => 'created',
                'type' => 'DateTimeField',
                'getter' => 'getCreated',
                'setter' => 'setCreated',
                'relationship' => null,
            ],
            'organisation' => [
                'handle' => 'manyProjectToOneOrganisation',
                'type' => 'Relationship',
                'getter' => 'getOrganisation',
                'setter' => 'setOrganisation',
                'relationship' => [
                    'class' => 'AppBundle\\Entity\\Organisation',
                    'plural' => false,
                    'kind' => 'many-to-one',
                ],
            ],
            'product' => [
                'handle' => 'manyProjectToOneProduct',
                'type' => 'Relationship',
                'getter' => 'getProduct',
                'setter' => 'setProduct',
                'relationship' => [
                    'class' => 'AppBundle\\Entity\\Product',
                    'plural' => false,
                    'kind' => 'many-to-one',
                ],
            ],
            'updated' => [
                'handle' => 'updated',
                'type' => 'DateTimeField',
                'getter' => 'getUpdated',
                'setter' => 'setUpdated',
                'relationship' => null,
            ],
            'projectSlug' => [
                'handle' => 'projectSlug',
                'type' => 'Slug',
                'getter' => 'getProjectSlug',
                'setter' => 'setProjectSlug',
                'relationship' => null,
            ],
            'uuid' => [
                'handle' => 'uuid',
                'type' => 'Uuid',
                'getter' => 'getUuid',
                'setter' => 'setUuid',
                'relationship' => null,
            ]
        ];
    }
}

namespace AppBundle\Entity;

class Organisation
{
    public static function getFields()
    {
        return [
            'created' => [
                'handle' => 'created',
                'type' => 'DateTimeField',
                'getter' => 'getCreated',
                'setter' => 'setCreated',
                'relationship' => null,
            ],
            'organisationName' => [
                'handle' => 'organisationName',
                'type' => 'TextInput',
                'getter' => 'getOrganisationName',
                'setter' => 'setOrganisationName',
                'relationship' => null,
            ],
            'organisationSlug' => [
                'handle' => 'organisationSlug',
                'type' => 'Slug',
                'getter' => 'getOrganisationSlug',
                'setter' => 'setOrganisationSlug',
                'relationship' => null,
            ],
            'organisationType' => [
                'handle' => 'organisationType',
                'type' => 'Choice',
                'getter' => 'getOrganisationType',
                'setter' => 'setOrganisationType',
                'relationship' => null,
            ],
            'updated' => [
                'handle' => 'updated',
                'type' => 'DateTimeField',
                'getter' => 'getUpdated',
                'setter' => 'setUpdated',
                'relationship' => null,
            ],
            'uuid' => [
                'handle' => 'uuid',
                'type' => 'Uuid',
                'getter' => 'getUuid',
                'setter' => 'setUuid',
                'relationship' => null,
            ]
        ];
    }
}

namespace AppBundle\Entity;

class AccountHasRole {

    // account, <- accountHasRole -> manyAccountHasRoleToOneAccount (manyToOne)

    public static function getFields()
    {
        return [
            'accountHasRoleSlug' => [
                'handle' => 'accountHasRoleSlug',
                'type' => 'Slug',
                'getter' => 'getAccountHasRoleSlug',
                'setter' => 'setAccountHasRoleSlug',
                'relationship' => null,
            ],
            'created' => [
                'handle' => 'created',
                'type' => 'DateTimeField',
                'getter' => 'getCreated',
                'setter' => 'setCreated',
                'relationship' => null,
            ],
            'account' => [
                'handle' => 'manyAccountHasRoleToOneAccount',
                'type' => 'Relationship',
                'getter' => 'getAccount',
                'setter' => 'setAccount',
                'relationship' => [
                    'class' => 'AppBundle\\Entity\\Account',
                    'plural' => false,
                    'kind' => 'many-to-one',
                ],
            ],
            'updated' => [
                'handle' => 'updated',
                'type' => 'DateTimeField',
                'getter' => 'getUpdated',
                'setter' => 'setUpdated',
                'relationship' => null,
            ],
            'uuid' => [
                'handle' => 'uuid',
                'type' => 'Uuid',
                'getter' => 'getUuid',
                'setter' => 'setUuid',
                'relationship' => null,
            ],
        ];
    }
}

namespace AppBundle\Entity;

class Account {

    // firstName, <- account
    // infix, <- account
    // lastName, <- account

    public static function getFields()
    {
        return [
            'accountSlug' => [
                'handle' => 'accountSlug',
                'type' => 'Slug',
                'getter' => 'getAccountSlug',
                'setter' => 'setAccountSlug',
                'relationship' => null,
            ],
            'created' => [
                'handle' => 'created',
                'type' => 'DateTimeField',
                'getter' => 'getCreated',
                'setter' => 'setCreated',
                'relationship' => null,
            ],
            'firstName' => [
                'handle' => 'firstName',
                'type' => 'TextInput',
                'getter' => 'getFirstName',
                'setter' => 'setFirstName',
                'relationship' => null,
            ],
            'infix' => [
                'handle' => 'infix',
                'type' => 'TextInput',
                'getter' => 'getInfix',
                'setter' => 'setInfix',
                'relationship' => null,
            ],
            'lastName' => [
                'handle' => 'lastName',
                'type' => 'TextInput',
                'getter' => 'getLastName',
                'setter' => 'setLastName',
                'relationship' => null,
            ],
            'updated' => [
                'handle' => 'updated',
                'type' => 'DateTimeField',
                'getter' => 'getUpdated',
                'setter' => 'setUpdated',
                'relationship' => null,
            ],
            'uuid' => [
                'handle' => 'uuid',
                'type' => 'Uuid',
                'getter' => 'getUuid',
                'setter' => 'setUuid',
                'relationship' => null,
            ]
        ];
    }
}

namespace This\Is\A;

/**
 * Class Product
 *
 * Why can't I implement the CommonSectionInterface?
 *
 * @package This\Is\A
 */
class Product {

    public static function getFields(): array
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
                    'class' => 'This\\Is\\A\\Price',
                    'plural' => true,
                    'kind' => 'one-to-many'
                ]
            ],
            'categories' => [
                'handle' => 'manyProductToManyCategory',
                'type' => 'Relationship',
                'getter' => 'getProductCategories',
                'setter' => 'addProductCategory',
                'relationship' => [
                    'class' => 'This\\Is\\A\\ProductCategory',
                    'plural' => true,
                    'kind' => 'many-to-many'
                ],
            ],
            'status' => [
                'handle' => 'manyProductToOneStatus',
                'type' => 'Relationship',
                'getter' => 'getStatus',
                'setter' => 'setStatus',
                'relationship' => [
                    'class' => 'This\\Is\\A\\Status',
                    'plural' => false,
                    'kind' => 'many-to-one',
                ],
            ]
        ];
    }

    /** @var string */
    protected $productSlug;

    /** @var string */
    protected $name;

    protected $prices;

    protected $categories;
}

namespace This\Is\A;

/**
 * Class Price
 * @package This\Is\A
 */
class Price
{
    public static function getFields() {
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
            ]
        ];
    }

    /** @var string */
    protected $priceSlug;

    /** @var string */
    protected $price;

    /** @var string */
    protected $currency;
}

namespace This\Is\A;

/**
 * Class ProductCategory
 *
 * This is many to many related to a product
 *
 * @package This\Is\A
 */
class ProductCategory
{
    public static function getFields() {
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

namespace This\Is\A;

class Status
{
    public static function getFields()
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
