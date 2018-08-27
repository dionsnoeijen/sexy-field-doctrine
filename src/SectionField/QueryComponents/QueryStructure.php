<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\Service\ReadOptionsInterface;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;
use Tardigrades\SectionField\ValueObject\Id;
use Tardigrades\SectionField\ValueObject\SectionConfig;
use Tardigrades\SectionField\ValueObject\Slug;

class QueryStructure implements QueryStructureInterface
{
    /** @var ReadOptionsInterface */
    private $readOptions;

    /** @var array */
    private $structure;

    /** @var FullyQualifiedClassName $section */
    private $section;

    const RELATIONSHIP = 'relationship';
    const KIND = 'kind';
    const TYPE = 'type';
    const TO = 'to';
    const AS = 'as';
    const FROM = 'from';
    const SLUG_FIELDS = 'slugFields';
    const WHERE = 'where';
    const SELECT = 'select';
    const HANDLE = 'handle';
    const CONDITION = 'condition';

    /**
     * @param ReadOptionsInterface $readOptions
     * @param SectionConfig|null $sectionConfig
     * @return array
     */
    public function get(
        ReadOptionsInterface $readOptions,
        SectionConfig $sectionConfig = null
    ): array {

        $this->readOptions = $readOptions;
        $this->section = $this->readOptions->getSection()[0];

        /** @var array $fetchFields */
        $fetchFields = $this->readOptions->getFetchFields();
        $field = $this->readOptions->getField();
        $sectionFQCN = (string) $this->section;

        $this->structure[self::FROM] = $this->section;
        $this->structure[self::RELATIONSHIP] = [];
        $this->structure[self::SLUG_FIELDS] = [];
        $this->structure[self::SELECT] = [];
        $this->structure[ReadOptions::LIMIT] = $this->readOptions->getLimit();
        $this->structure[ReadOptions::OFFSET] = $this->readOptions->getOffset();
        $this->structure[ReadOptions::ORDER_BY] = $this->readOptions->getOrderBy();

        if (!is_null($field)) {
            // The field handle may exist of several properties.
            // Whereas the last one is the field, and everything before are relationships.
            // For example: If we have: 'project:product:slug'. Project and product are
            // relationships, and the slug is the slug of product.
            // So add the first two to the fetchFields.
            $fieldHandle = explode(':', key($field));
            foreach ($fieldHandle as $key => $propertyName) {
                if ($key <= count($fieldHandle) - 2 && !in_array($propertyName, $fetchFields)) {
                    $fetchFields[] = $propertyName;
                }
            }
        }

        if (!is_null($fetchFields)) {
            $this->findRelationships($fetchFields, $sectionFQCN);
            $this->aliasRelationships();
            $this->orderRelationships();
            $this->findSelect($fetchFields, $sectionFQCN);
            $this->addSlugFields();
        } else {
            $handle = lcfirst($this->section->getClassName()) . '.*';
            $select = [ self::HANDLE => $handle ];
            $this->structure[self::SELECT][] = $select;
        }

        $this->addWhere();

        return $this->structure;
    }

    /**
     * A fetch fields string could contain relationships:
     * find them.
     *
     * @param array $fetchFields
     * @param string $fullyQualifiedClassName
     */
    private function findRelationships(
        array $fetchFields,
        string $fullyQualifiedClassName
    ): void {

        $entityProperties = $fullyQualifiedClassName::getFields();
        $fullyQualifiedClassName = FullyQualifiedClassName::fromString($fullyQualifiedClassName);

        $this->structure[self::SLUG_FIELDS][
            lcfirst($fullyQualifiedClassName->getClassName())
        ] = $this->findSlugField($entityProperties);

        $this->addRelationship(
            $entityProperties,
            $fetchFields,
            $fullyQualifiedClassName
        );
    }

    /**
     * Find how to select properties of entities.
     *
     * @param array $fetchFields
     * @param string $fullyQualifiedClassName
     */
    private function findSelect(
        array $fetchFields,
        string $fullyQualifiedClassName
    ): void {

        $this->addSelect(
            $fullyQualifiedClassName::getFields(),
            $fetchFields,
            FullyQualifiedClassName::fromString($fullyQualifiedClassName)->getClassName()
        );

        foreach ($this->structure[self::RELATIONSHIP] as $relationship) {
            $to = (string) $relationship[self::TO];
            $entityProperties = $to::getFields();
            $this->addSelect($entityProperties, $fetchFields, $relationship[self::AS]);
        }
    }

    private function addSlugFields(): void
    {
        foreach ($this->structure[self::SLUG_FIELDS] as $property=>$slugField) {
            $this->structure[self::SELECT][] = [
                self::HANDLE => $property . '.' . $slugField . ' AS ' . $property . '_' . $slugField
            ];
        }
    }

    /**
     * Some relationships are complicated in the sense that they are related
     * over several levels of depth and aliassed, figure that out.
     */
    private function aliasRelationships(): void
    {
        $removeSlug = null;
        foreach ($this->structure[self::RELATIONSHIP] as $index => $relationship) {
            $as = $this->findAsByTo($relationship[self::FROM]);
            if (count($as) > 1 && !isset($relationship['dynamic'])) {
                foreach ($as as $key => $alias) {
                    $newRelationship = [
                        self::FROM => $relationship[self::FROM],
                        self::TO => $relationship[self::TO],
                        self::AS => $alias . '_' .lcfirst($relationship[self::TO]->getClassName()),
                        self::KIND => $relationship[self::KIND],
                        self::CONDITION => $alias . '_' . lcfirst($relationship[self::TO]->getClassName()) . ' = ' . $alias,
                        'dynamic' => true
                    ];
                    if (!in_array($newRelationship, $this->structure[self::RELATIONSHIP])) {
                        $this->structure[self::RELATIONSHIP][] = $newRelationship;
                        $original = lcfirst($relationship[self::TO]->getClassName());
                        // @todo: Only if fetch fields contains slug that is.
                        $this->structure[self::SLUG_FIELDS][$newRelationship[self::AS]] =
                            $this->structure[self::SLUG_FIELDS][$original];
                        $removeSlug = $original;
                    }
                }
                unset($this->structure[self::RELATIONSHIP][$index]);
                if (!is_null($removeSlug)) {
                    unset($this->structure[self::SLUG_FIELDS][$removeSlug]);
                }
            }
        }
    }

    private function orderRelationships(): void
    {
        usort($this->structure[self::RELATIONSHIP], function($a, $b) {
            return $a[self::FROM] === $b[self::TO];
        });
    }

    /**
     * @param array $entityProperties
     * @param array $fetchFields
     * @param FullyQualifiedClassName $fullyQualifiedClassName
     */
    private function addRelationship(
        array $entityProperties,
        array $fetchFields,
        FullyQualifiedClassName $fullyQualifiedClassName
    ): void {
        foreach ($entityProperties as $fieldProperty => $field) {
            if (in_array($fieldProperty, $fetchFields) && !empty($field[self::RELATIONSHIP])) {
                $kind = $field[self::RELATIONSHIP][self::KIND];
                $to = FullyQualifiedClassName::fromString($field[self::RELATIONSHIP]['class']);
                $relationship = [
                    self::FROM => $fullyQualifiedClassName,
                    self::TO => $to,
                    self::AS => $fieldProperty,
                    self::KIND => $kind,
                    self::CONDITION => $fieldProperty . ' = ' . lcfirst($fullyQualifiedClassName->getClassName()) . '.' . $fieldProperty
                ];
                if (!in_array($relationship, $this->structure[self::RELATIONSHIP])) {
                    $this->structure[self::RELATIONSHIP][$fieldProperty] = $relationship;
                }
                $class = $field[self::RELATIONSHIP]['class'];
                $this->findRelationships($fetchFields, $class);
            }
        }
    }

    /**
     * @param array $entityProperties
     * @param array $fetchFields
     * @param string $as
     */
    private function addSelect(
        array $entityProperties,
        array $fetchFields,
        string $as
    ): void {
        foreach ($entityProperties as $fieldProperty => $field) {
            if (in_array($fieldProperty, $fetchFields) && empty($field['relationship'])) {

                // Make sure the FQCN is in the getFields() of the entity, allowing for third parties
                // to have their own namespaces
                $fieldType = 'Tardigrades\\FieldType\\' . $field['type'] . '\\' . $field['type'] . 'Query';

                $fieldProperties = [$fieldProperty];
                if (class_exists($fieldType) && method_exists($fieldType, 'select')) {
                    $fieldProperties = $fieldType::select($fieldProperty);
                }
                foreach ($fieldProperties as $fieldProperty) {
                    $handle = lcfirst($as) . '.' . $fieldProperty;
                    $handle = $handle . ' AS ' . str_replace('.', '_', $handle);
                    $select = [self::HANDLE => $handle];
                    if (!in_array($select, $this->structure[self::SELECT])) {
                        $this->structure[self::SELECT][] = $select;
                    }
                }
            }
        }
    }

    private function addWhere(): void
    {
        if (empty($this->structure[self::WHERE])) {
            $this->structure[self::WHERE] = [];
        }

        $this->addWhereId();
        $this->addWhereSlug();

        $field = $this->readOptions->getField();

        if (!is_null($field)) {
            if (!is_array($field[key($field)])) {
                $this->addOneWhereField();
            } else {
                $this->addWhereInField();
            }
        }
    }

    private function addWhereId(): void
    {
        /** @var Id $id */
        $id = $this->readOptions->getId();
        if (!is_null($id)) {
            $this->structure[self::WHERE][] = [
                'fullyQualifiedClassName' => $this->section,
                'parameters' => [
                    'key' => 'id',
                    'property' => 'id',
                    'value' => $id->toInt()
                ]
            ];
        }
    }

    private function addWhereSlug(): void
    {
        /** @var Slug $slug */
        $slug = $this->readOptions->getSlug();
        if (!is_null($slug)) {
            $this->structure[self::WHERE][] = [
                'fullyQualifiedClassName' => $this->section,
                'parameters' => [
                    'key' => 'slug',
                    'property' => 'slug',
                    'value' => (string) $slug
                ]
            ];
        }
    }

    private function addOneWhereField(): void
    {
        $field = $this->readOptions->getField();
        if (!is_null($field)) {
            $this->structure[self::WHERE][] = [
                'fullyQualifiedClassName' => $this->section,
                'parameters' => [
                    'key' => 'field',
                    'property' => end(explode(':', key($field))),
                    'value' => $field[key($field)]
                ]
            ];
        }
    }

    private function addWhereInField(): void
    {
        $field = $this->readOptions->getField();
        $fieldParts = explode(':', key($field));

        $propertyName = end($fieldParts);
        $propertyOf = count($fieldParts) > 1 ? $fieldParts[count($fieldParts) - 2] : $this->section->getClassName();
        if ($propertyName === 'slug') {
            $propertyName = $this->structure[self::SLUG_FIELDS][$propertyOf];
        }

        if (!is_null($field)) {
            $this->structure[self::WHERE][] = [
                self::WHERE => $fieldParts[count($fieldParts) - 2],
                'parameters' => [
                    'key' => 'fields',
                    'property' => $propertyOf . '.' . $propertyName,
                    'value' => $field[key($field)]
                ]
            ];
        }
    }

    /**
     * @param FullyQualifiedClassName $to
     * @return array
     */
    private function findAsByTo(FullyQualifiedClassName $to): array
    {
        $result = [];
        foreach ($this->structure[self::RELATIONSHIP] as $relationship) {
            if ((string) $relationship[self::TO] === (string) $to) {
                $result[] = $relationship[self::AS];
            }
        }
        return $result;
    }

    /**
     * @param array $entityProperties
     * @return null|string
     */
    private function findSlugField(array $entityProperties): ?string
    {
        // It will add the real slug handles to the fetch fields
        foreach ($entityProperties as $fieldProperty => $field) {
            if ($field[self::TYPE] === 'Slug') {
                return $fieldProperty;
            }
        }
        return null;
    }
}
