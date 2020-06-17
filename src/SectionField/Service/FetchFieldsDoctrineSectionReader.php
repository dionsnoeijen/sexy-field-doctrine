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
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
    }

    /**
     * @param OptionsInterface $options
     * @param SectionConfig|null $sectionConfig
     * @return \ArrayIterator
     * @throws EntryNotFoundException
     * @throws NoEntityManagerFoundForSection
     */
    public function read(OptionsInterface $options, SectionConfig $sectionConfig = null): \ArrayIterator
    {
        $results = $this->buildQuery($options)->getQuery()->getResult();
        if (count($results) === 0) {
            throw new EntryNotFoundException;
        }
        return new \ArrayIterator($results);
    }

    public function flush(): void
    {
        if (!is_null($this->entityManager)) {
            $this->entityManager->flush();
        }
    }

    /**
     * Like ::read, but returns a nested array, like ['foo' => ['bar' => 'baz']] instead of ['foo:bar' => 'baz'].
     * It's a bit slower.
     * @param OptionsInterface $options
     * @return \ArrayIterator
     * @throws EntryNotFoundException
     * @throws NoEntityManagerFoundForSection
     */
    public function readNested(OptionsInterface $options): \ArrayIterator
    {
        $results = $this->buildQuery($options)->getQuery()->getResult();
        if (count($results) === 0) {
            throw new EntryNotFoundException;
        }
        return new \ArrayIterator(
            array_map(
                'static::makeNested',
                $results
            )
        );
    }

    /**
     * Build a DQL query to fetch all the fields you need in one go, without lazy loading.
     * Returns a QueryBuilder that can be used to further enhance the query.
     * @param OptionsInterface $readOptions
     * @return ORM\QueryBuilder
     * @throws NoEntityManagerFoundForSection
     */
    public function buildQuery(OptionsInterface $readOptions): ORM\QueryBuilder
    {
        $section = $readOptions->getSection()[0];
        $this->determineEntityManager($section);

        $options = $readOptions->toArray();
        $builder = $this->entityManager->createQueryBuilder();

        if (!array_key_exists(ReadOptions::FIELD, $options)) {
            $options[ReadOptions::FIELD] = [];
        }
        if (array_key_exists(ReadOptions::SLUG, $options)) {
            $options[ReadOptions::FIELD]['slug'] = $options[ReadOptions::SLUG];
        }

        $fields = array_merge(
            explode(',', $options[ReadOptions::FETCH_FIELDS]),
            ...array_map(
                function (string $queryField): array {
                    return static::tail(explode(':', $queryField));
                },
                array_merge(
                    array_keys($options[ReadOptions::FIELD]),
                    array_key_exists(ReadOptions::ORDER_BY, $options) ?
                        array_keys($options[ReadOptions::ORDER_BY]) : []
                )
            )
        );
        $fields = array_unique($fields);
        if ($fields === ['']) {
            throw new InvalidFetchFieldsQueryException("Not selecting any fields");
        }

        // The root entity is the basic section we join everything else to and start all lookups on.
        $root = $options[ReadOptions::SECTION];
        $builder->from($root, static::simplifyClass($root));

        /*
         * We build a queue of fields to select and tables to join.
         * Each member of the queue is a chain of items with a 'field' key and a 'class' key.
         * This chain describes how to look the field up on the root entity.
         * The 'field' is the name of the field on the previous entity in the chain, and the 'class' is the class of
         * the current entity in the chain.
         */
        $queue = [[['field' => static::simplifyClass($root), 'class' => $root]]];
        $didSelect = false;
        while ($queue) {
            $entityPath = array_shift($queue);
            if (count($entityPath) > 5 || static::hasDuplicates($entityPath)) {
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
                        $builder->addSelect("{$name}.{$field} AS {$name}:{$field}");
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

        $wheres = [];
        $num = 1;
        foreach ($options[ReadOptions::FIELD] as $field => $value) {
            $fieldParts = array_merge(
                [static::simplifyClass($root)],
                explode(':', $field)
            );
            if (static::end($fieldParts) === 'slug') {
                // The slug field is actually called something like "entitySlug", not "slug", but we'd like to
                // support it this way as well.
                // This is only a guess, because tracking the correct class is hard. It won't work if
                // the field name doesn't match the class name. In that case, just use the full slug field name.
                $fieldParts[count($fieldParts) - 1] = $fieldParts[count($fieldParts) - 2] . 'Slug';
            }
            $lookupEntity = implode(':', static::tail($fieldParts));
            $fieldName = static::end($fieldParts);
            $fieldPath = "$lookupEntity.$fieldName";
            if (is_array($value)) {
                $wheres[] = "$fieldPath IN (?$num)";
            } else {
                $wheres[] = "$fieldPath = (?$num)";
            }
            $builder->setParameter($num, $value);
            $num += 1;
        }
        if (count($wheres) > 0) {
            $builder->where($builder->expr()->andX(...$wheres));
        }

        if (array_key_exists(ReadOptions::LIMIT, $options)) {
            $builder->setMaxResults($options[ReadOptions::LIMIT]);
        }

        if (array_key_exists(ReadOptions::ORDER_BY, $options)) {
            $builder->orderBy(
                static::simplifyClass($root) . ':' . key($options[ReadOptions::ORDER_BY]),
                current($options[ReadOptions::ORDER_BY])
            );
        }
        return $builder;
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
            $keyParts = explode(':', $key);
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
     * Check if any fields appear in an entity path multiple times, to avoid weird loops.
     * @param array $entityPath
     * @return bool
     */
    private static function hasDuplicates(array $entityPath): bool
    {
        $found = [];
        foreach ($entityPath as $field) {
            $key = $field['field'] . "\0" . $field['class'];
            if (array_key_exists($key, $found)) {
                return true;
            }
            $found[$key] = true;
        }
        return false;
    }

    /**
     * Turn a class name into a simpler name for in a query.
     * @param string $className
     * @return string
     */
    private static function simplifyClass(string $className): string
    {
        return lcfirst(static::end(explode('\\', $className)));
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
        return implode(':', $fields);
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
}
