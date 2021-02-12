<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @coversDefaultClass Tardigrades\SectionField\Service\DoctrineSectionCreator
 * @covers ::<private>
 */
final class DoctrineSectionCreatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var EntityManagerInterface|Mockery\Mock */
    private $entityManager;

    /** @var Registry|Mockery\MockInterface */
    private $registry;

    public function setUp(): void
    {
        $this->entityManager = Mockery::mock(EntityManagerInterface::class);
        $this->registry = Mockery::mock(Registry::class);
    }

    /**
     * @test
     * @covers ::__construct
     */
    public function it_creates()
    {
        $section = new DoctrineSectionCreator($this->registry);
        $this->assertInstanceOf(DoctrineSectionCreator::class, $section);
    }

    /**
     * @test
     * @covers ::save
     */
    public function it_saves()
    {
        $data = Mockery::mock('alias:Tardigrades\SectionField\Generator\CommonSectionInterface')->makePartial();

        $section = new DoctrineSectionCreator($this->registry);

        $this->givenWeHaveAValidEntityAssignedToAManager();

        $this->entityManager->shouldReceive('persist')
            ->once()
            ->with($data);

        $this->entityManager->shouldReceive('flush')
            ->once();

        $section->save($data);
    }

    /**
     * @test
     * @covers ::persist
     */
    public function it_persists()
    {
        $data = Mockery::mock('alias:Tardigrades\SectionField\Generator\CommonSectionInterface')->makePartial();

        $section = new DoctrineSectionCreator($this->registry);

        $this->givenWeHaveAValidEntityAssignedToAManager();

        $this->entityManager->shouldReceive('persist')
            ->once()
            ->with($data);

        $this->entityManager->shouldReceive('flush')
            ->never();

        $section->persist($data);
    }

    /**
     * @test
     * @covers ::flush
     */
    public function it_flushes()
    {
        $section = new DoctrineSectionCreator(
            $this->registry,
            $this->entityManager
        );
        $this->entityManager
            ->shouldReceive('flush')
            ->once();

        $section->flush();
    }

    private function givenWeHaveAValidEntityAssignedToAManager()
    {
        $configuration = Mockery::mock(Configuration::class);
        $configuration->shouldReceive('getEntityNamespaces')
            ->once()
            ->andReturn(['Tardigrades\SectionField\Generator\CommonSectionInterface']);

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
