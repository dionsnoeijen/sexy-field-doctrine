<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tardigrades\SectionField\ValueObject\Before;
use Tardigrades\SectionField\ValueObject\SectionConfig;

/**
 * @coversDefaultClass \Tardigrades\SectionField\Service\DoctrineSectionReader
 * @covers ::<private>
 */
final class DoctrineSectionReaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var EntityManagerInterface|Mockery\MockInterface */
    private $entityManager;

    /** @var QueryBuilder|Mockery\MockInterface */
    private $queryBuilder;

    /** @var FetchFieldsQueryBuilder|Mockery\MockInterface */
    private $fetchFieldsQueryBuilder;

    /** @var Registry|Mockery\MockInterface */
    private $registry;

    public function setUp()
    {
        $this->entityManager = Mockery::mock(EntityManagerInterface::class);
        $this->registry = Mockery::mock(Registry::class);
        $this->queryBuilder = Mockery::mock(QueryBuilder::class);
        $this->fetchFieldsQueryBuilder = Mockery::mock(FetchFieldsQueryBuilder::class);
    }

    /**
     * @test
     * @covers ::__construct
     */
    public function it_constructs()
    {
        $reader = new DoctrineSectionReader($this->registry, $this->fetchFieldsQueryBuilder);
        $this->assertInstanceOf(DoctrineSectionReader::class, $reader);
    }

    /**
     * @test
     * @covers ::read
     */
    public function it_reads()
    {
        $optionData = [
            'section' => 'Tardigrades\SectionField\Generator\CommonSectionInterfaceStub'
        ];

        $configData = [
            'section' => [
                'name' => 'nameTo',
                'handle' => 'handle',
                'fields' => ['a' => 'b'],
                'default' => 'default',
                'namespace' => 'namespace'
            ]
        ];

        $reader = new DoctrineSectionReader($this->registry, $this->fetchFieldsQueryBuilder);
        $sectionConfig = SectionConfig::fromArray($configData);
        $readOptions = ReadOptions::fromArray($optionData);

        $this->givenWeHaveAValidEntityAssignedToAManager();

        $query = Mockery::mock('alias:Query')->makePartial()
        ->shouldReceive('getResult')->andReturn(['dont know'])->getMock();

        $this->entityManager->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilder);

        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with('commonSectionInterfaceStub');

        $this->queryBuilder->shouldReceive('from')
            ->once()
            ->with('Tardigrades\SectionField\Generator\CommonSectionInterfaceStub', 'commonSectionInterfaceStub');

        $this->queryBuilder->shouldReceive('getQuery')
            ->once()
            ->andReturn($query);

        $reader->read($readOptions, $sectionConfig);
    }

    /**
     * @test
     * @covers ::read
     */
    public function it_fails_with_no_results()
    {
        $optionData = [
            'section' => 'Tardigrades\SectionField\Generator\CommonSectionInterfaceStub'
        ];

        $configData = [
            'section' => [
                'name' => 'nameTo',
                'handle' => 'handle',
                'fields' => ['a' => 'b'],
                'default' => 'default',
                'namespace' => 'namespace'
            ]
        ];

        $reader = new DoctrineSectionReader($this->registry, $this->fetchFieldsQueryBuilder);
        $sectionConfig = SectionConfig::fromArray($configData);
        $readOptions = ReadOptions::fromArray($optionData);

        $this->givenWeHaveAValidEntityAssignedToAManager();

        $query = Mockery::mock('alias:Query')->makePartial()
            ->shouldReceive('getResult')->andReturn([])->getMock();

        $this->entityManager->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilder);

        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with('commonSectionInterfaceStub');

        $this->queryBuilder->shouldReceive('from')
            ->once()
            ->with(
                'Tardigrades\SectionField\Generator\CommonSectionInterfaceStub',
                'commonSectionInterfaceStub'
            );

        $this->queryBuilder->shouldReceive('getQuery')
            ->once()
            ->andReturn($query);

        $this->expectException(EntryNotFoundException::class);

        $reader->read($readOptions, $sectionConfig);
    }

    /**
     * @test
     * @covers ::read
     */
    public function it_reads_everything()
    {
        $date = new \DateTime('2017-10-21T15:03');

        $optionData = [
            'id' => 1,
            'slug' => 'section-one',
            'section' => 'Tardigrades\SectionField\Service\CommonSectionInterfaceStub',
            'sectionId' => 2,
            'limit' => 3,
            'offset' => 4,
            'orderBy' => ['some' => 'asc'],
            'before' => (string) Before::fromDateTime($date),
            'after' => (string) Before::fromDateTime($date),
            'localeEnabled' => true,
            'locale' => 'en_EN',
            'search' => 'search',
            'field' => ['color' => 'purple']
        ];

        $configData = [
            'section' => [
                'name' => 'nameTo',
                'handle' => 'handle',
                'fields' => ['a' => 'b'],
                'default' => 'default',
                'namespace' => 'namespace'
            ]
        ];

        $reader = new DoctrineSectionReader($this->registry, $this->fetchFieldsQueryBuilder);
        $this->givenWeHaveAValidEntityAssignedToAManager();
        $this->registry->shouldReceive('getDefaultManagerName')
            ->once()
            ->andReturn('defaultManager');
        $this->registry->shouldReceive('getManager')
            ->once()
            ->andReturn($this->entityManager);
        $sectionConfig = SectionConfig::fromArray($configData);
        $readOptions = ReadOptions::fromArray($optionData);

        $query = Mockery::mock('alias:Query')->makePartial()
            ->shouldReceive('getResult')->andReturn(['dont know'])->getMock();

        $this->entityManager->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilder);

        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with('commonSectionInterfaceStub');

        $this->queryBuilder->shouldReceive('from')
            ->once()
            ->with('Tardigrades\SectionField\Service\CommonSectionInterfaceStub', 'commonSectionInterfaceStub');

        $this->queryBuilder->shouldReceive('where')
            ->times(4);

        $this->queryBuilder->shouldReceive('andWhere')
            ->once();

        $this->queryBuilder->shouldReceive('setParameter')
            ->once()
            ->with('color', 'purple');

        $this->queryBuilder->shouldReceive('setParameter')
            ->once()
            ->with('id', 1);

        $this->queryBuilder->shouldReceive('setParameter')
            ->once()
            ->with('slug', 'section-one');

        $this->queryBuilder->shouldReceive('setMaxResults')
            ->once()
            ->with(3);

        $this->queryBuilder->shouldReceive('setFirstResult')
            ->once()
            ->with(4);

        $this->queryBuilder->shouldReceive('orderBy')
            ->once()
            ->with('commonSectionInterfaceStub.some', 'asc');

        $this->queryBuilder->shouldReceive('setParameter')
            ->once()
            ->with('before', '2017-10-21T15:03');

        $this->queryBuilder->shouldReceive('setParameter')
            ->once()
            ->with('after', '2017-10-21T15:03');

        $this->queryBuilder->shouldReceive('getQuery')
            ->once()
            ->andReturn($query);

        $reader->read($readOptions, $sectionConfig);
    }

    /**
     * @test
     * @covers ::read
     */
    public function it_can_handle_negative_selections()
    {
        $optionData = [
            'section' => 'Tardigrades\SectionField\Service\CommonSectionInterfaceStub',
            'field' => [
                'not:color' => ['purple']
            ]
        ];

        $configData = [
            'section' => [
                'name' => 'nameTo',
                'handle' => 'handle',
                'fields' => ['a' => 'b'],
                'default' => 'default',
                'namespace' => 'namespace'
            ]
        ];

        $reader = new DoctrineSectionReader($this->registry, $this->fetchFieldsQueryBuilder);
        $sectionConfig = SectionConfig::fromArray($configData);
        $readOptions = ReadOptions::fromArray($optionData);

        $this->givenWeHaveAValidEntityAssignedToAManager();
        $this->registry->shouldReceive('getDefaultManagerName')
            ->once()
            ->andReturn('defaultManager');
        $this->registry->shouldReceive('getManager')
            ->once()
            ->andReturn($this->entityManager);

        $query = Mockery::mock('alias:Query')->makePartial()
            ->shouldReceive('getResult')->andReturn(['dont know'])->getMock();

        $this->entityManager->shouldReceive('createQueryBuilder')
            ->once()
            ->andReturn($this->queryBuilder);

        $this->queryBuilder->shouldReceive('select')
            ->once()
            ->with('commonSectionInterfaceStub');

        $this->queryBuilder->shouldReceive('from')
            ->once()
            ->with('Tardigrades\SectionField\Service\CommonSectionInterfaceStub', 'commonSectionInterfaceStub');

        $this->queryBuilder->shouldReceive('getQuery')
            ->once()
            ->andReturn($query);

        $this->queryBuilder->shouldReceive('expr->notIn')
            ->once()
            ->with('commonSectionInterfaceStub.color', ':color');

        $this->queryBuilder->shouldReceive('andWhere')
            ->once()
            ->with(null);

        $this->queryBuilder->shouldReceive('setParameter')
            ->once()
            ->with('color', ['purple']);

        $reader->read($readOptions, $sectionConfig);
    }

    /**
     * @test
     * @covers ::flush
     */
    public function it_flushes()
    {
        $this->entityManager->shouldReceive('flush')->once();
        (new DoctrineSectionReader($this->registry, $this->fetchFieldsQueryBuilder, $this->entityManager))->flush();
    }

    private function givenWeHaveAValidEntityAssignedToAManager()
    {
        $configuration = Mockery::mock(Configuration::class);
        $configuration->shouldReceive('getEntityNamespaces')
            ->once()
            ->andReturn(['Tardigrades\SectionField\Generator\CommonSectionInterfaceStub']);

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
