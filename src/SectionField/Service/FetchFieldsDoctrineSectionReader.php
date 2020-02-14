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

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\QueryComponents\WhereParser;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;
use Tardigrades\SectionField\ValueObject\SectionConfig;

/**
 * This SectionReader constructs a DQL query to eagerly read only the fields you ask for.
 * That means you get an array with fields instead of the full object, which is faster to execute but
 * a bit harder to work with.
 *
 * ::buildQuery and ::makeNested are public methods, to be used for more advanced behavior, like caching or
 * added query components.
 */
class FetchFieldsDoctrineSectionReader extends Doctrine implements ReadSectionInterface
{
    const COMPARITORS = [
        ReadOptions::EQUAL,
        ReadOptions::NOT_EQUAL,
        ReadOptions::LARGER_THAN,
        ReadOptions::SMALLER_THAN,
        ReadOptions::SMALLER_THAN_OR_EQUAL_TO,
        ReadOptions::LARGER_THAN_OR_EQUAL_TO
    ];

    const WHERE_AND_OR = [
        ReadOptions::AND,
        ReadOptions::OR
    ];

    const CONVERT_WHERE_AND_OR = [
        ReadOptions::AND => 'AND',
        ReadOptions::OR => 'OR'
    ];

    private int $lastDepth = -1;
    private bool $addDepth = false;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * @param ReadOptionsInterface $options
     * @param SectionConfig|null $sectionConfig
     * @return \ArrayIterator
     * @throws EntryNotFoundException
     * @throws NoEntityManagerFoundForSection
     */
    public function read(ReadOptionsInterface $options, SectionConfig $sectionConfig = null): \ArrayIterator
    {
        $results = $this->buildQuery($options)->getQuery()->getResult();
        if (count($results) === 0) {
            throw new EntryNotFoundException;
        }
        return new \ArrayIterator(
            array_map(
                'static::removeTopLevelKey',
                array_map(
                    'static::makeNested',
                    $results
                )
            )
        );
    }

    public function flush(): void
    {
        if (!is_null($this->entityManager)) {
            $this->entityManager->flush();
        }
    }

    /**
     * Build a DQL query to fetch all the fields you need in one go, without lazy loading.
     * Returns a QueryBuilder that can be used to further enhance the query.
     * @param ReadOptionsInterface $options
     * @return ORM\QueryBuilder
     */
    public function buildQuery(ReadOptionsInterface $options): ORM\QueryBuilder
    {
        $root = $options->getSection()[0];
        $this->determineEntityManager($root);
        $builder = $this->entityManager->createQueryBuilder();
        $fields = $this->parseFields($options);

        // The root entity is the basic section we join everything else to and start all lookups on.
        $builder->from((string) $root, (string) $root->toHandle());
        $this->addJoins($options, $builder, $root, $fields);
        $this->parseWhere($options, $builder, $root);
        $this->addWhere($options, $builder, $root);

        $builder->setMaxResults($options->getLimit()->toInt());
        $orderBy = $options->getOrderBy()->toArray();
        $builder->orderBy(
            (string) $root->toHandle() . '.' . key($orderBy),
            strtoupper(current($orderBy))
        );
        return $builder;
    }

    private function parseFields(ReadOptionsInterface $options): array
    {
//        if (array_key_exists(ReadOptions::SLUG, $options) && !empty($options[ReadOptions::SLUG])) {
//            $options[ReadOptions::FIELD]['slug'] = $options[ReadOptions::SLUG];
//        }

        $fields = array_merge(
            $options->getFetchFields(),
            ...array_map(
                function (string $queryField): array {
                    return static::tail(explode(':', $queryField));
                },
                array_merge(
                    array_keys($options->getField()),
                    array_keys($options->getOrderBy()->toArray())
                )
            )
        );
        $fields = array_unique($fields);
        if ($fields === ['']) {
            throw new InvalidFetchFieldsQueryException("Not selecting any fields");
        }

        return $fields;
    }

    /**
     * We build a queue of fields to select and tables to join.
     * Each member of the queue is a chain of items with a 'field' key and a 'class' key.
     * This chain describes how to look the field up on the root entity.
     * The 'field' is the name of the field on the previous entity in the chain, and the 'class' is the class of
     * the current entity in the chain.
     */
    private function addJoins(ReadOptionsInterface $options, ORM\QueryBuilder $builder, FullyQualifiedClassName $root, array $fields): void
    {
        $queue = [[[
            'field' => (string) $root->toHandle(),
            'class' => (string) $root
        ]]];
        $didSelect = false;
        while ($queue) {
            $entityPath = array_shift($queue);
            if (count($entityPath) > $options->getDepth()) {
                continue;
            }
            $pathEnd = static::end($entityPath);

            /** @var CommonSectionInterface|string $class */
            $class = $pathEnd['class'];
            $fieldName = $pathEnd['field'];

            $classMetadata = $class::fieldInfo();
            $name = static::implodeEntityPath($entityPath);

            if (count($entityPath) > 1) {
                // Because this is not the root entity, add a join
                $parentName = static::implodeEntityPath(static::tail($entityPath));
                $builder->leftJoin("$parentName.$fieldName", $name);
            }
            foreach ($fields as $field) {
                if ($field === 'slug') {
                    $field = static::findSlug($classMetadata);
                }
                if (array_key_exists($field, $classMetadata)) {
                    $fieldInfo = $classMetadata[$field];
                    if (is_null($fieldInfo['relationship'])) {
                        $didSelect = true;
                        $builder->addSelect("{$name}.{$field} AS {$name}_{$field}");
                    } else {
                        // This field points to a related entity, so add another join to the queue
                        $newEntityPath = $entityPath;
                        $newEntityPath[] = [
                            'field' => $field,
                            'class' => $fieldInfo['relationship']['class']
                        ];
                        $queue[] = $newEntityPath;
                    }
                }
            }
        }
        if (!$didSelect) {
            throw new InvalidFetchFieldsQueryException("Could not find any of the fields");
        }
    }



    private function parseWhere(ReadOptionsInterface $options, ORM\QueryBuilder $builder): void
    {
        $wheres = $this->fields($options);
        exit;
    }

    private function addWhere(ReadOptionsInterface $options, ORM\QueryBuilder $builder, FullyQualifiedClassName $root): void
    {
        $wheres = [];
        $num = 1;
        foreach ($options->getField() as $field => $value) {
            $field = explode(':', $field);
            $isNot = false;
            if ($field[0] === 'not') {
                array_shift($field);
                $isNot = true;
            }
            $fieldParts = array_merge(
                [(string) $root->toHandle()],
                $field
            );

            if (static::end($fieldParts) === 'slug') {
                // The slug field is actually called something like "entitySlug", not "slug", but we'd like to
                // support it this way as well.
                // This is only a guess, because tracking the correct class is hard. It won't work if
                // the field name doesn't match the class name. In that case, just use the full slug field name.
                $fieldParts[count($fieldParts) - 1] = $fieldParts[count($fieldParts) - 2] . 'Slug';
            }
            $lookupEntity = implode('_', static::tail($fieldParts));
            $fieldName = static::end($fieldParts);
            $fieldPath = "$lookupEntity.$fieldName";
            if (is_array($value)) {
                if (!$isNot) {
                    $wheres[] = "$fieldPath IN (?$num)";
                } else {
                    $wheres[] = "$fieldPath NOT IN (?$num)";
                }
            } else {
                if (is_null($value) || $value === 'null') {
                    $wheres[] = "$fieldPath IS NULL";
                    $value = null;
                } else {
                    if ($isNot) {
                        $wheres[] = "$fieldPath != (?$num) OR $fieldPath IS NULL";
                    } else {
                        $wheres[] = "$fieldPath = (?$num)";
                    }
                }
            }
            $builder->setParameter($num, $value);
            $num += 1;
        }

        if (count($wheres) > 0) {
            $builder->where($builder->expr()->andX(...$wheres));
        }
    }

    /**
     * Make a flat array nested.
     * @param array $data
     * @return array
     */
    public static function makeNested(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $keyParts = explode('_', $key);
            $current =& $result;
            foreach (static::tail($keyParts) as $keyPart) {
                if (!array_key_exists($keyPart, $current)) {
                    $current[$keyPart] = [];
                }
                $current =& $current[$keyPart];
            }
            $current[static::end($keyParts)] = $value;
        }
        return $result;
    }

    /**
     * Reduce an entity path to the name that entity gets in the DQL, by linking field names with underscores
     * @param array[] $entityPath
     * @return string
     */
    private static function implodeEntityPath(array $entityPath): string
    {
        $fields = [];
        foreach ($entityPath as $item) {
            $fields[] = $item['field'];
        }
        return implode('_', $fields);
    }

    /**
     * Find the name of a section's slug field.
     * @param array $classMetadata
     * @return string
     */
    private static function findSlug(array $classMetadata): string
    {
        foreach ($classMetadata as $name => $info) {
            if ($info['type'] === 'Slug') {
                return $name;
            }
        }
        throw new InvalidFetchFieldsQueryException("Class doesn't have a slug field");
    }

    /**
     * Return the last value in an array.
     * The built-in end() function has two drawbacks:
     * - It only works on variables, not arbitrary expressions, because the array is passed by reference
     * - It has the side effect of setting the internal pointer to the last element
     * @param array $array
     * @return mixed
     */
    private static function end(array $array)
    {
        return end($array);
    }

    /**
     * Return an array without its last element. Complements ::end().
     * @param array $array
     * @return array
     */
    private static function tail(array $array): array
    {
        array_pop($array);
        return $array;
    }

    private static function removeTopLevelKey(array $data): array
    {
        return $data[key($data)];
    }

    private function fields(
        ReadOptionsInterface $options
    ): string {
        $wheres = $this->conditions($options);
        //$dql = self::toDQL($wheres);
        echo '<pre>';
        //print_r($dql);
        print_r($wheres);
        echo '</pre>';
        exit;
        return $dql;
    }

    private function conditions(
        ReadOptionsInterface $options,
        array $fields = [],
        string $andOr = '',
        array $wheres = [],
        string $dql = '',
        int $depth = 0
    ): array {
        if (empty($fields)) {
            $fields = $options->getField();
        }
        foreach ($fields as $key=>$field) {
            if (in_array($andOr, self::WHERE_AND_OR)) {
                $conditionToSql = $this->conditionToDQL($andOr, $key, $field, $depth);
                $wheres[$key] = $conditionToSql;
                if (in_array($key, self::WHERE_AND_OR)) {
                    $dql .= self::CONVERT_WHERE_AND_OR[$andOr] . ' ( ';
                }
                foreach ($conditionToSql as $condition) {
                    $dql .= $condition . ' ' . $depth . "\n";
                }
            }
            if (in_array($key, self::WHERE_AND_OR) ||
                !in_array($key, self::COMPARITORS)
            ) {
                if (in_array($key, self::WHERE_AND_OR)) {
                    $wheres[$key] = $this->conditions(
                        $options, $field, $key, [], $dql, $depth
                    );
                    $depth -= 1;
                } else {
                    $wheres[$key] = $this->conditions(
                        $options, $field, $key, $wheres[$key], $dql, $depth
                    );
                    $depth += 1;
                }
            }
        }

        /**
        ReadOptions::FIELD => [
        ReadOptions::AND => [
        'project:product:productSlug' => [ ReadOptions::EQUAL => [':fields'] ],
        'accountHasRole:account:firstName' => [ ReadOptions::NOT_EQUAL => 'anoniem' ],
        'participantSessionAppointmentDate' => [
        ReadOptions::LARGER_THAN_OR_EQUAL_TO => ':after',
        ReadOptions::SMALLER_THAN => ':before',
        ReadOptions::OR => [
        ReadOptions::AND => [
        ':allowNulls' => [ ReadOptions::EQUAL => true ],
        'participantSessionAppointmentDate' => [ ReadOptions::EQUAL => null ]
        ]
        ]
        ]
        ]
        ]
         */

        /**
        WHERE product.productSlug IN(:fields)
        AND NOT accountHasRole_account.firstName = 'anoniem'
        AND (
        participantSession.participantSessionAppointmentDate >= (:laterThan)
        participantSession.participantSessionAppointmentDate < (:soonerThan)
        OR (
        (:allowNulls) = TRUE
        AND participantSession.participantSessionAppointmentDate IS NULL
        )
        )
         */

        echo "\n\n" . '---' . "\n\n\n";
        echo $dql;

        return $wheres;
    }

    private function conditionToDQL(string $andOr, string $handle, array $condition, int $depth): array
    {
        $add = [];

        $path = explode(':', $handle);
        $handle = array_pop($path);
        $fieldPath = implode('_', $path) . '.' . $handle;
        $addDepth = '';

        $andOr = self::CONVERT_WHERE_AND_OR[$andOr];
        if ($depth === 0) {
            $andOr = 'WHERE';
        }

        if (count($condition) > 1) {
            $addDepth = '(';
        }
        foreach ($condition as $comparitor=>$value) {
            if (in_array($comparitor, self::COMPARITORS)) {
                if (is_null($value)) {
                    if ($comparitor === ReadOptions::EQUAL) {
                        $comparitor = 'IS';
                    } else if ($comparitor === ReadOptions::NOT_EQUAL) {
                        $comparitor = 'IS NOT';
                    }
                    $add[] = "$andOr $addDepth $fieldPath $comparitor NULL";
                } else if (!is_array($value)) {
                    if ($comparitor === ReadOptions::NOT_EQUAL) {
                        $andOr .= ' NOT';
                    }
                    $add[] = "$andOr $addDepth $fieldPath = '$value'";
                } else {
                    $value = implode(',', $value);
                    $add[] = "$andOr $addDepth $fieldPath IN ($value)";
                }
                $addDepth = '';
            }
        }
        return $add;
    }
}
