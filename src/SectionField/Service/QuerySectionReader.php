<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\QueryComponents\From;
use Tardigrades\SectionField\QueryComponents\Limit;
use Tardigrades\SectionField\QueryComponents\ManyToMany;
use Tardigrades\SectionField\QueryComponents\ManyToOne;
use Tardigrades\SectionField\QueryComponents\Offset;
use Tardigrades\SectionField\QueryComponents\OneToMany;
use Tardigrades\SectionField\QueryComponents\OneToOne;
use Tardigrades\SectionField\QueryComponents\OrderBy;
use Tardigrades\SectionField\QueryComponents\QueryStructure;
use Tardigrades\SectionField\QueryComponents\QueryStructureInterface;
use Tardigrades\SectionField\QueryComponents\Select;
use Tardigrades\SectionField\QueryComponents\TransformResultsInterface;
use Tardigrades\SectionField\QueryComponents\Where;
use Tardigrades\SectionField\ValueObject\SectionConfig;

/**
 * Class QuerySectionReader
 *
 * This will build one query for getting data from the database as opposed
 * to the default lazy_loading system that will make many queries to do the same.
 *
 * @package Tardigrades\SectionField\Service
 */
class QuerySectionReader implements ReadSectionInterface
{
    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var QueryStructureInterface */
    private $queryStructure;

    /** @var TransformResultsInterface */
    private $transform;

    /** @var \Doctrine\ORM\QueryBuilder */
    private $queryBuilder;

    /**
     * QuerySectionReader constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param QueryStructureInterface $queryStructure
     * @param TransformResultsInterface $transform
     * @param QueryBuilder $queryBuilder
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        QueryStructureInterface $queryStructure,
        TransformResultsInterface $transform,
        QueryBuilder $queryBuilder = null
    ) {
        $this->entityManager = $entityManager;
        $this->queryStructure = $queryStructure;
        $this->transform = $transform;
        $this->queryBuilder = $queryBuilder;

        if (is_null($queryBuilder)) {
            $this->queryBuilder = $this->entityManager->createQueryBuilder();
        }
    }

    public function read(ReadOptionsInterface $readOptions, SectionConfig $sectionConfig = null): \ArrayIterator
    {
        $structure = $this->queryStructure->get($readOptions, $sectionConfig);

        From::add($this->queryBuilder, $structure);
        Select::add($this->queryBuilder, $structure);
        foreach ($structure[QueryStructure::RELATIONSHIP] as $relationship) {
            switch ($relationship[QueryStructure::KIND]) {
                case OneToMany::ONE_TO_MANY:
                    OneToMany::add($this->queryBuilder, $relationship);
                    break;
                case ManyToOne::MANY_TO_ONE:
                    ManyToOne::add($this->queryBuilder, $relationship);
                    break;
                case ManyToMany::MANY_TO_MANY:
                    ManyToMany::add($this->queryBuilder, $relationship);
                    break;
                case OneToOne::ONE_TO_ONE:
                    OneToOne::add($this->queryBuilder, $relationship);
                    break;
            }
        }
        Where::add($this->queryBuilder, $structure);
        Limit::add($this->queryBuilder, $structure);
        Offset::add($this->queryBuilder, $structure);
        OrderBy::add($this->queryBuilder, $structure);

        return new \ArrayIterator(
//            $this->transform->intoHierarchy(
//                $this->getResults()
//            )
        );
    }

    private function getResults(): array
    {
        return $this->queryBuilder->getQuery()->getResult();
    }
}
