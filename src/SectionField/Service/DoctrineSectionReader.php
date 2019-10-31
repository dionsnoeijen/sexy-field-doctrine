<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\ValueObject\Slug;
use Tardigrades\SectionField\ValueObject\After;
use Tardigrades\SectionField\ValueObject\Before;
use Tardigrades\SectionField\ValueObject\CreatedField;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;
use Tardigrades\SectionField\ValueObject\Id;
use Tardigrades\SectionField\ValueObject\Limit;
use Tardigrades\SectionField\ValueObject\Offset;
use Tardigrades\SectionField\ValueObject\OrderBy;
use Tardigrades\SectionField\ValueObject\SectionConfig;
use Tardigrades\SectionField\ValueObject\SlugField;

class DoctrineSectionReader extends Doctrine implements ReadSectionInterface
{
    /** @var QueryBuilder */
    private $queryBuilder;

    /** FetchFieldsQueryBuilder */
    private $fetchFieldsQueryBuilder;

    public function __construct(
        Registry $registry,
        FetchFieldsQueryBuilder $fetchFieldsQueryBuilder,
        EntityManagerInterface $entityManager = null
    ) {
        $this->fetchFieldsQueryBuilder = $fetchFieldsQueryBuilder;
        $this->entityManager = $entityManager;
        parent::__construct($registry);
    }

    /**
     * @param ReadOptionsInterface $readOptions
     * @param SectionConfig|null $sectionConfig
     * @return \ArrayIterator
     * @throws EntryNotFoundException
     * @throws NoEntityManagerFoundForSection
     */
    public function read(ReadOptionsInterface $readOptions, SectionConfig $sectionConfig = null): \ArrayIterator
    {
        $query = $readOptions->getQuery();
        if (!is_null($query)) {
            $results = $this->manualQuery($readOptions);
            if (count($results) <= 0) {
                throw new EntryNotFoundException();
            }
            return new \ArrayIterator((array) $results);
        }
        /** @var FullyQualifiedClassName $section */
        $section = $readOptions->getSection()[0];
        $this->determineEntityManager($section);

        $fetchFields = $readOptions->getFetchFields();
        $this->queryBuilder = $this->entityManager->createQueryBuilder();

        if (!is_null($fetchFields)) {
            if ($this->fetchFieldsContainsMany($fetchFields, $section) &&
                !is_null($readOptions->getRelate())
            ) {
                $fetchFields = null;
            }
        }

        $formatResult = false;
        if (!is_null($fetchFields)) {
            $this->queryBuilder = $this->fetchFieldsQueryBuilder->getQuery($this->queryBuilder, $section, $fetchFields);
            $formatResult = true;
        } else {
            $this->addSectionToQuery($section);
        }

        $this->addIdToQuery($readOptions->getId(), $section);
        $this->addSlugToQuery(
            $readOptions->getSlug(),
            $sectionConfig->getSlugField(),
            $section
        );

        $this->addFieldToQuery(
            $readOptions->getField(),
            $readOptions->getRelate(),
            $section
        );
        $this->addJoinToQuery(
            $readOptions->getJoin(),
            $section
        );
        $this->addLimitToQuery($readOptions->getLimit());
        $this->addOffsetToQuery($readOptions->getOffset());

        $this->addOrderByToQuery(
            $readOptions->getOrderBy(),
            $section
        );
        $this->addBeforeToQuery(
            $sectionConfig->getCreatedField(),
            $readOptions->getBefore(),
            $section
        );
        $this->addAfterToQuery(
            $sectionConfig->getCreatedField(),
            $readOptions->getAfter(),
            $section
        );
        $query = $this->queryBuilder->getQuery();
        $results = $query->getResult();

        if ($formatResult) {
            $results = $this->formatResult($results, $section);
        }

        if (count($results) <= 0) {
            throw new EntryNotFoundException();
        }

        return new \ArrayIterator($results);
    }

    public function flush(): void
    {
        // This assumes the entity managers has been determined before
        // by calling persist before flushing.
        if (!is_null($this->entityManager)) {
            $this->entityManager->flush();
        }
    }

    /**
     * The fetch fields class is momentarily not ready to handle
     * to-many relationships, so skip them.
     *
     * @todo: Enhance the fetch fields class to handle many relationships aswel
     *
     * @param array $fetchFields
     * @param FullyQualifiedClassName $section
     * @return bool
     */
    private function fetchFieldsContainsMany(array $fetchFields, FullyQualifiedClassName $section): bool
    {
        $sectionClass = (string) $section;
        $fields = $sectionClass::fieldInfo();
        foreach ($fetchFields as $fetchField) {
            if (!is_null($this->isManyRelationship($fetchField, $fields))) {
                return true;
            }
        }

        return false;
    }

    private function isOneRelationship(string $fieldProperty, array $fields): ?string
    {
        if (key_exists($fieldProperty, $fields)) {
            try {
                switch ($fields[$fieldProperty]['relationship']['kind']) {
                    case 'many-to-one':
                    case 'one-to-one':
                        return $fields[$fieldProperty]['relationship']['class'];
                }
            } catch (\Exception $exception) {
                // Field is no relationship
            }
        }

        return null;
    }

    private function isManyRelationship(string $fieldProperty, array $fields): ?string
    {
        if (key_exists($fieldProperty, $fields)) {
            try {
                switch ($fields[$fieldProperty]['relationship']['kind']) {
                    case 'one-to-many':
                    case 'many-to-many':
                        return $fields[$fieldProperty]['relationship']['class'];
                }
            } catch (\Exception $exception) {
                // Field is no relationship
            }
        }

        return null;
    }

    private function formatResult(array $results, FullyQualifiedClassName $section): array
    {
        $className = lcfirst($section->getClassName());
        $parsed = [];
        foreach ($results as $fields) {
            $entry = [];
            foreach ($fields as $field => &$value) {
                $field = str_replace($className . '_', '', $field);
                $pieces = explode('_', $field);
                foreach (array_reverse($pieces) as $piece) {
                    $value = [ $piece => $value ];
                }
                $entry = array_merge_recursive($value, $entry);
            }
            $parsed[] = $entry;
        }
        return $parsed;
    }

    private function addSectionToQuery(FullyQualifiedClassName $section): void
    {
        $className = lcfirst((string) $section->getClassName());
        $this->queryBuilder->select((string) $className);
        $this->queryBuilder->from((string) $section, (string) $className);
    }

    private function addIdToQuery(Id $id = null, FullyQualifiedClassName $section): void
    {
        if ($id instanceof Id) {
            $className = lcfirst((string) $section->getClassName());
            $this->queryBuilder->where((string) $className . '.id = :id');
            $this->queryBuilder->setParameter('id', $id->toInt());
        }
    }

    private function addSlugToQuery(
        Slug $slug = null,
        SlugField $slugField = null,
        FullyQualifiedClassName $section
    ): void {
        if ($slug instanceof Slug && $slugField instanceof SlugField) {
            $className = lcfirst((string) $section->getClassName());
            $this->queryBuilder->where((string) $className . '.' . (string) $slugField . '= :slug');
            $this->queryBuilder->setParameter('slug', (string)$slug);
        }
    }

    /**
     * @todo: This has become too complicated, extract into logical parts
     *
     * @param array|null $fields
     * @param array|null $relate
     * @param FullyQualifiedClassName $section
     */
    private function addFieldToQuery(
        array $fields = null,
        array $relate = null,
        FullyQualifiedClassName $section
    ): void {

        if (!empty($fields)) {
            $className = lcfirst((string) $section->getClassName());
            foreach ($fields as $handle => $fieldValue) {
                $isNot = false;
                $handle = explode(':', $handle);
                if (count($handle) > 1 && $handle[0] === 'not') {
                    $isNot = true;
                    $handle = (string) $handle[1];
                }
                if (is_array($handle)) {
                    $handle = (string) $handle[0];
                }

                $sectionEntity = (string) $section;
                $fields = $sectionEntity::fieldInfo();
                $sectionEntityClass = lcfirst((string) $section->getClassName());

                $joinOne = $this->isOneRelationship($handle, $fields);
                $joinMany = $this->isManyRelationship($handle, $fields);

                // If we are dealing with a ONE relationship, join the table first
                if (!is_null($joinOne) && !is_null($relate)) {
                    $this->addJoinOne($joinOne, $sectionEntityClass, $handle, $fieldValue, $relate);
                }

                // If we are dealing with a MANY relationship, join the table first
                if (!is_null($joinMany)) {
                    $this->addJoinMany($joinMany, $sectionEntityClass, $handle, $fieldValue, $relate);
                }

                // If not a relationship, or not a related one relationship (so it's a regular field or a one field)
                if ((is_null($joinOne) && is_null($joinMany)) || is_null($relate)) {
                    // If we have multiple field values, make an IN query
                    if (is_array($fieldValue)) {
                        $addOrNull = false;
                        if ($key = array_search('null', $fieldValue) !== false) {
                            $addOrNull = true;
                            unset($fieldValue[$key]);
                        }
                        if ($isNot) {
                            $this->queryBuilder->andWhere(
                                $this->queryBuilder->expr()->notIn(
                                    (string) $className . '.' . (string) $handle,
                                    ':' . $handle
                                )
                            );
                        } else {
                            $this->queryBuilder->andWhere(
                                $this->queryBuilder->expr()->in(
                                    (string) $className . '.' . (string) $handle,
                                    ':' . $handle
                                )
                            );
                        }

                        if ($addOrNull) {
                            $this->queryBuilder->orWhere((string)$className . '.' . (string)$handle . ' IS NULL');
                        }
                        $this->queryBuilder->setParameter($handle, $fieldValue);

                        // Otherwise, just make a where query
                    } else {
                        if (is_null($fieldValue) || $fieldValue === 'null') {
                            $assign = ' IS NULL';
                            $fieldValue = null;
                        } else {
                            if ($isNot) {
                                $assign = '!= :' . $handle . ' OR ' . $handle . ' IS NULL';
                            } else {
                                $assign = '= :' . $handle;
                            }
                        }

                        $this->queryBuilder->andWhere((string) $className . '.' . (string) $handle . $assign);
                        if (!is_null($fieldValue)) {
                            $this->queryBuilder->setParameter($handle, (string) $fieldValue);
                        }
                    }
                }
            }
        }
    }

    private function addJoinOne(
        string $join,
        string $sectionEntityClass,
        $handle,
        $fieldValue,
        array $relate = null
    ): void {

        $this->queryBuilder->innerJoin(
            $join, // Project
            $handle, // project
            'WITH',
            $sectionEntityClass . '.' . $handle . ' = ' . $handle
        );

        $handle = !empty($relate[0]) ? $handle . '.' . $relate[0] : $handle;
        if (is_null($fieldValue)) {
            $assign = ' IS NULL';
        } else {
            $assign = '= :fieldValue';
        }

        try {
            $relateTo = $join::fieldInfo()[$relate[0]]['relationship']['class'];
            $relateHandle = $this->getRelateHandle($relate, $relateTo);
            $this->queryBuilder->leftJoin(
                $relateTo,
                $relate[0],
                'WITH',
                $relate[0] . ' = ' . $handle
            );
        } catch (\Exception $exception) {
            // This was no relationship class
            $relateHandle = '';
        }

        if (!empty($relate)) {
            if (is_array($fieldValue)) {
                $this->queryBuilder->andWhere(
                    $this->queryBuilder->expr()->in(
                        $relate[0] . (string)$relateHandle,
                        ':fieldValue'
                    )
                );
                $this->queryBuilder->setParameter('fieldValue', $fieldValue);
            } else {
                $this->queryBuilder->andWhere($relate[0] . $relateHandle . $assign);
                if (!is_null($fieldValue)) {
                    $this->queryBuilder->setParameter('fieldValue', (string)$fieldValue);
                }
            }
        }
    }

    private function addJoinMany(
        string $join,
        string $sectionEntityClass,
        $handle,
        $fieldValue,
        array $relate = null
    ): void {

        $this->queryBuilder->innerJoin(
            $join,
            $handle,
            'WITH',
            $handle . '.' . $sectionEntityClass .' = ' . $sectionEntityClass . '.id'
        );

        // Is the field on a related section?
        $handle = !empty($relate[0]) ? $handle . '.' . $relate[0] : $handle;
        if (is_null($fieldValue)) {
            $assign = ' IS NULL';
        } else {
            $assign = '= :fieldValue';
        }

        try {
            $relateTo = $join::fieldInfo()[$relate[0]]['relationship']['class'];
            $relateHandle = $this->getRelateHandle($relate, $relateTo);
            $this->queryBuilder->leftJoin(
                $relateTo,
                $relate[0],
                'WITH',
                $relate[0] . ' = ' . $handle
            );
        } catch (\Exception $exception) {
            // This was no relationship class
            $relateHandle = '';
        }

        if (is_array($fieldValue)) {
            $this->queryBuilder->andWhere(
                $this->queryBuilder->expr()->in(
                    $relate[0] . (string) $relateHandle,
                    ':fieldValue'
                )
            );
            $this->queryBuilder->setParameter('fieldValue', $fieldValue);
        } else {
            $this->queryBuilder->andWhere($relate[0] . $relateHandle . $assign);
            if (!is_null($fieldValue)) {
                $this->queryBuilder->setParameter('fieldValue', (string) $fieldValue);
            }
        }
    }

    private function getRelateHandle(array $relate, string $relateTo): string
    {
        $relateHandle = '.id';
        if (!empty($relate[1])) {
            $relateHandle = '.' . $relate[1];
            if ($relate[1] === 'slug') {
                $relateHandle = '.' . $this->findSlugfield($relateTo::fieldInfo());
            }
        }
        return $relateHandle;
    }

    private function findSlugfield(array $fields): string
    {
        foreach ($fields as $handle => $field) {
            if ($field['type'] === 'Slug') {
                return $handle;
            }
        }
    }

    private function addJoinToQuery(
        array $joins = null,
        FullyQualifiedClassName $section
    ) {
        if (!empty($joins)) {
            $className = lcfirst((string) $section->getClassName());
            foreach ($joins as $handle => $fieldValue) {
                $this->queryBuilder->innerJoin(
                    (string)$className . '.' . $handle,
                    $handle,
                    'WITH',
                    $handle . '.id = ' . (string) $fieldValue
                );
            }
        }
    }

    private function addLimitToQuery(Limit $limit = null): void
    {
        if ($limit instanceof Limit) {
            $this->queryBuilder->setMaxResults($limit->toInt());
        }
    }

    private function addOffsetToQuery(Offset $offset = null): void
    {
        if ($offset instanceof Offset) {
            $this->queryBuilder->setFirstResult($offset->toInt());
        }
    }

    private function addOrderByToQuery(OrderBy $orderBy = null, FullyQualifiedClassName $section = null): void
    {
        if ($orderBy instanceof OrderBy && $section instanceof FullyQualifiedClassName) {
            $className = lcfirst((string) $section->getClassName());
            $field = (string) $className . '.' . (string) $orderBy->getHandle();
            if (strpos((string) $orderBy->getHandle(), '.')) {
                $field = (string) $orderBy->getHandle();
            }
            $this->queryBuilder->orderBy(
                $field,
                (string) $orderBy->getSort()
            );
        }
    }

    private function addBeforeToQuery(
        CreatedField $createdField,
        Before $before = null,
        FullyQualifiedClassName $section = null
    ): void {
        if ($before instanceof Before && $section instanceof FullyQualifiedClassName) {
            $className = lcfirst((string) $section->getClassName());
            $this->queryBuilder->where($className . '.' . (string) $createdField . ' < :before');
            $this->queryBuilder->setParameter('before', (string) $before);
        }
    }

    private function addAfterToQuery(
        CreatedField $createdField,
        After $after = null,
        FullyQualifiedClassName $section = null
    ): void {
        if ($after instanceof After && $section instanceof FullyQualifiedClassName) {
            $className = lcfirst((string) $section->getClassName());
            $this->queryBuilder->where($className. '.' . (string) $createdField . ' > :after');
            $this->queryBuilder->setParameter('after', (string) $after);
        }
    }

    /**
     * @param ReadOptionsInterface $readOptions
     * @return \ArrayIterator
     */
    private function manualQuery(ReadOptionsInterface $readOptions): \ArrayIterator
    {
        $this->determineEntityManager();
        $query = $readOptions->getQuery();
        $query = $this->entityManager->createQuery($query);

        $parameters = $readOptions->getQueryParameters();
        if (!empty($parameters) && is_array($parameters)) {
            $query->setParameters($parameters);
        }

        $limit = $readOptions->getLimit();
        if ($limit instanceof Limit) {
            $query->setMaxResults($limit->toInt());
        }
        $offset = $readOptions->getOffset();
        if ($offset instanceof Offset) {
            $this->queryBuilder->setFirstResult($offset->toInt());
        }
        $results = $query->getResult();
        return new \ArrayIterator((array) $results);
    }
}
