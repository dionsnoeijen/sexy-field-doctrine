<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;
use Tardigrades\SectionField\ValueObject\Handle;

/**
 * This class will determine a query based on the fields you need and
 * a starting point which is a section.
 *
 * Class FetchFieldsQueryBuilder
 * @package Tardigrades\SectionField\Service
 */
class FetchFieldsQueryBuilder
{
    /** @var array */
    private $reflections = [];

    /** @var array */
    private $selectProperties = [];

    /** @var array */
    private $joins = [];

    /** @var array */
    private $as = [];

    /** @var SectionManagerInterface */
    private $sectionManager;

    /** @var int */
    const MAX_DEPTH = 5;

    /**
     * FetchFieldsQueryBuilder constructor.
     * @param SectionManagerInterface $sectionManager
     */
    public function __construct(SectionManagerInterface $sectionManager)
    {
        $this->sectionManager = $sectionManager;
    }

    /**
     * I might want to add caching of the created query here
     *
     * @param QueryBuilder $queryBuilder
     * @param FullyQualifiedClassName $section
     * @param array $fetchFields
     * @return QueryBuilder
     */
    public function getQuery(
        QueryBuilder $queryBuilder,
        FullyQualifiedClassName $section,
        array $fetchFields
    ): QueryBuilder {
        return $this->buildQuery($queryBuilder, $section, $fetchFields);
    }

    /**
     * Run the steps required to make the query
     *
     * @param QueryBuilder $queryBuilder
     * @param FullyQualifiedClassName $section
     * @param array $fetchFields
     * @return QueryBuilder
     */
    private function buildQuery(
        QueryBuilder $queryBuilder,
        FullyQualifiedClassName $section,
        array $fetchFields
    ): QueryBuilder {

        // 1: Get the entities that belong to the fields we want to query
        $this->buildReflections($section, $fetchFields);
        reset($this->reflections);
        $start = $this->reflections[key($this->reflections)];

        // 2: Make select properties
        $this->makeSelectProperties($start);

        // 3: Make joins
        $this->makeJoins($start);

        // 4: And bring it all together in the query builder
        $queryBuilder->addSelect($this->selectProperties);
        $queryBuilder->from($start['reflection']->getName(), $start['propertyName']);
        array_multisort($this->joins);
        foreach ($this->joins as $join => $order) {
            $joinOn = explode(' ', $join);
            $queryBuilder->leftJoin($joinOn[0], $joinOn[1]);
        }

        return $queryBuilder;
    }

    /**
     * @param array $reflection
     * @param string|null $prevPropertyName
     * @param int $depth
     */
    private function makeJoins(array $reflection, string $prevPropertyName = null, int $depth = 0): void
    {
        if (!empty($reflection['sectionDetails'])) {
            $fields = $reflection['sectionDetails']['fields'];
        } else {
            $fields = $reflection['fields'];
        }
        $propertyName = $reflection['propertyName'];

        $depth++;
        foreach ($fields as $key => $field) {
            if (empty($field['relatedToSection']) && !empty($field['propertyName'])) {
                if (!is_null($prevPropertyName) && $prevPropertyName !== $propertyName) {
                    $as = ltrim($prevPropertyName.'_'.$propertyName, '_');
                    $propertyNameAlias = $this->selectJoinName($prevPropertyName);
                    $propertySelector = "{$propertyNameAlias}.{$propertyName} {$as}";
                    $this->joins[$propertySelector] = $key;
                }
            } else {
                if ($depth < self::MAX_DEPTH) {
                    $this->makeJoins($field, $propertyName, $depth);
                }
            }
        }
    }

    /**
     * @param array $reflection
     * @param string|null $prevPropertyName
     * @param int $depth
     */
    private function makeSelectProperties(array $reflection, string $prevPropertyName = null, int $depth = 0): void
    {
        if (!empty($reflection['sectionDetails'])) {
            $fields = $reflection['sectionDetails']['fields'];
        } else {
            $fields = $reflection['fields'];
        }
        $propertyName = $reflection['propertyName'];

        $depth++;
        foreach ($fields as $field) {
            if (empty($field['relatedToSection']) && !empty($field['propertyName'])) {
                if ($propertyName !== $prevPropertyName) {
                    $as = ltrim($prevPropertyName . '_' . $propertyName, '_');
                    $this->as[$as] = $propertyName;
                    $propertySelector = "{$as}.{$field['propertyName']} {$as}_{$field['propertyName']}";
                    $this->selectProperties[$propertySelector] = $propertySelector;
                }
            } else {
                if ($depth < self::MAX_DEPTH) {
                    $this->makeSelectProperties($field, $propertyName, $depth);
                }
            }
        }
    }

    /**
     * @param FullyQualifiedClassName $section
     * @param array $fetchFields
     * @param int $depth
     */
    private function buildReflections(FullyQualifiedClassName $section, array $fetchFields, int $depth = 0): void
    {
        $className = $section->getClassName();
        if (!key_exists($className, $this->reflections)) {
            try {
                $this->reflections[$className] = [
                    'reflection' => new \ReflectionClass((string)$section),
                    'propertyName' => lcfirst($className),
                    'depth' => $depth
                ];

                $properties = $this->reflections[$className]['reflection']->getProperties();
                foreach ($properties as $property) {
                    if (in_array($property->getName(), $fetchFields)) {
                        $docComment = $property->getDocComment();
                        $docComment = str_replace('/** @var ', '', $docComment);
                        $docComment = str_replace('?', '', $docComment);
                        $docComment = str_replace(' */', '', $docComment);
                        if (!isset($this->reflections[$className]['fields'])) {
                            $this->reflections[$className]['fields'] = [];
                        }
                        $this->reflections[$className]['fields'][] = ['propertyName' => $property->getName()];
                        try {
                            if (lcfirst($docComment) !== 'string' && lcfirst($docComment) !== '\DateTime') {
                                $section = $this->sectionManager->readByHandle(
                                    Handle::fromString(lcfirst($docComment))
                                );
                                end($this->reflections[$className]['fields']);
                                $key = key($this->reflections[$className]['fields']);
                                $this->reflections[$className]['fields'][$key]['relatedToSection'] = $docComment;
                                $this->buildReflections(
                                    $section->getConfig()->getFullyQualifiedClassName(),
                                    $fetchFields,
                                    $depth++
                                );
                            }
                        } catch (SectionNotFoundException $exception) {
                            // Just go on
                        }
                    }
                }
            } catch (\ReflectionException $exception) {
                // Implement logging or something?
            }
        }
        $this->stackReflections();
    }

    /**
     * @param string $propertyName
     * @return string
     */
    private function selectJoinName(string $propertyName): string
    {
        $result = array_search($propertyName, $this->as, true);
        return $result;
    }

    /**
     * Nesting of reflected entities
     */
    private function stackReflections()
    {
        foreach ($this->reflections as &$reflection) {
            foreach ($reflection['fields'] as &$field) {
                if (!empty($field['relatedToSection'])) {
                    if (isset($this->reflections[$field['relatedToSection']])) {
                        $field['sectionDetails'] = $this->reflections[$field['relatedToSection']];
                    }
                }
            }
        }
    }
}
