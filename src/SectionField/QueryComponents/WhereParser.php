<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

class WhereParser
{
    const AND = '&&';
    const OR = '||';
    const EQUAL = '=';
    const NOT_EQUAL = '!=';
    const LARGER_THAN = '>';
    const SMALLER_THAN = '<';
    const LARGER_THAN_OR_EQUAL_TO = '>=';
    const SMALLER_THAN_OR_EQUAL_TO = '<=';

    const COMPARATORS = [
        self::EQUAL,
        self::NOT_EQUAL,
        self::LARGER_THAN,
        self::SMALLER_THAN,
        self::SMALLER_THAN_OR_EQUAL_TO,
        self::LARGER_THAN_OR_EQUAL_TO
    ];

    const AND_OR = [
        self::AND,
        self::OR
    ];

    const CONVERT_AND_OR = [
        self::AND => 'AND',
        self::OR => 'OR',
        0 => 'WHERE'
    ];

    private static function isAndOr(string $andOr = ''): bool
    {
        return in_array($andOr, self::AND_OR);
    }

    private static function isComparator(string $comparator = ''): bool
    {
        return in_array($comparator, self::COMPARATORS);
    }

    private static function convertAndOr(string $andOr, bool $startWithWhere = false): string
    {
        if ($startWithWhere) {
            return self::CONVERT_AND_OR[0];
        }
        return self::CONVERT_AND_OR[$andOr];
    }

    private static function makeTabs(int $count): string
    {
        $tabs = '';
        for ($i = 0 ; $i < $count ; $i++) {
            $tabs .= '    ';
        }
        return $tabs;
    }

    /**
     * @param string $key
     * @param $value
     * @return string
     * @throws InvalidFieldsConfigurationException
     */
    private static function parseSelector(string $key, $value): string
    {
        if (is_array($value)) {
            foreach ($value as &$v) {
                if ($v[0] !== ':') {
                    $v = '\'' . $v . '\'';
                }
            }
            return ' IN(' . implode(', ', $value) . ')';
        }
        if (is_null($value)) {
            return ' IS NULL';
        }
        if (self::isComparator($key)) {
            if (is_string($value) && $value[0] !== ':') {
                return " $key '$value'";
            }
            if (is_string($value)) {
                return " $key $value";
            }
            if (is_bool($value)) {
                return ' ' . $key . ' ' . ($value ? 'TRUE' : 'FALSE');
            }
        }

        throw new InvalidFieldsConfigurationException();
    }

    /**
     * Inserts a start for a select block with multiple lines like: 'AND ('
     * This is later closed by ::endGroupedAndOr
     *
     * @param $key
     * @param string $andOr
     * @param bool $hasMultiple
     * @param int $count
     * @param bool $addedConnector
     * @param int $depth
     * @param string $add
     * @param bool $startWithWhere
     */
    private static function startGroupedAndOr(
        $key,
        string $andOr,
        bool $hasMultiple,
        int $count,
        bool $addedConnector,
        int $depth,
        string &$add,
        bool &$startWithWhere
    ): void {
        if (!is_integer($key) &&
            self::isAndOr($andOr) &&
            $hasMultiple &&
            $count === 0 &&
            !$addedConnector &&
            $depth > 0
        ) {
            $add .= sprintf(
                "\n%s%s (",
                self::makeTabs($depth-1),
                self::convertAndOr($andOr, $startWithWhere)
            );
            $startWithWhere = true;
        }
    }

    /**
     * Inserts the select condition
     *
     * @param $key
     * @param $value
     * @param string $andOr
     * @param string $fieldHandle
     * @param int $depth
     * @param string $add
     * @param bool $addedConnector
     * @param bool $startWithWhere
     * @throws InvalidFieldsConfigurationException
     */
    private static function insertCondition(
        $key,
        $value,
        string $andOr,
        string $fieldHandle,
        int $depth,
        string &$add,
        bool &$addedConnector,
        bool &$startWithWhere
    ): void {
        if (!is_integer($key) &&
            !(
                self::isAndOr($andOr) &&
                self::isAndOr($fieldHandle)
            ) &&
            (
                !empty($andOr) ||
                !self::isAndOr($key)
            )
        ) {
            if (self::isAndOr($key) && $depth > 0) {
                $add .= sprintf(
                    "\n%s%s (",
                    self::makeTabs($depth),
                    self::convertAndOr($key, $startWithWhere)
                );
                $addedConnector = true;
                $startWithWhere = true;
            } else {
                $add .= sprintf(
                    "\n%s%s %s%s",
                    self::makeTabs($depth),
                    self::convertAndOr($andOr, $startWithWhere),
                    $fieldHandle,
                    self::parseSelector($key, $value)
                );
                $addedConnector = false;
                $startWithWhere = false;
            }
        }
    }

    /**
     * Closes a block that started with 'AND (' or 'OR (' with ')'
     *
     * @param int $depth
     * @param int $count
     * @param int $howMany
     * @param bool $hasMultiple
     * @param string $add
     */
    private static function endGroupedAndOr(
        int $depth,
        int $count,
        int $howMany,
        bool $hasMultiple,
        string &$add
    ): void {
        if ($depth > 0 && $count === $howMany && !$hasMultiple) {
            $add .= sprintf("\n%s)", self::makeTabs($depth-1));
        }
    }

    /**
     * Builds the where conditions for an advanced ReadOptions::FIELDS condition
     *
     * @param array $fields
     * @param int $depth
     * @param string $dql
     * @param string $andOr
     * @param string $fieldHandle
     * @param bool $hasMultiple
     * @param bool $addedConnector
     * @param int $howMany
     * @param bool $startWithWhere
     * @return string
     * @throws InvalidFieldsConfigurationException
     */
    public static function makeDQL(
        array $fields,
        int $depth = -1,
        string $dql = '',
        string $andOr = '',
        string $fieldHandle = '',
        bool $hasMultiple = false,
        bool $addedConnector = false,
        int $howMany = 0,
        bool $startWithWhere = true
    ): string {
        $add = '';
        $count = 0;
        foreach ($fields as $key => $value) {
            self::startGroupedAndOr(
                $key, $andOr, $hasMultiple, $count,
                $addedConnector, $depth, $add, $startWithWhere
            );
            self::insertCondition(
                $key, $value, $andOr, $fieldHandle,
                $depth, $add, $addedConnector, $startWithWhere
            );
            $count++;
            if (is_array($value)) {
                $hasMultiple = false;
                if (count($value) > 1) {
                    $depth++;
                    $hasMultiple = true;
                    $howMany = count($value);
                }
                $add .= self::makeDQL(
                    $value, $depth, $dql, self::isAndOr($key) ? $key : $andOr,
                    $key, $hasMultiple, $addedConnector, $howMany, $startWithWhere
                );
                $startWithWhere = false;
            }
            self::endGroupedAndOr(
                $depth, $count, $howMany,
                $hasMultiple, $add
            );
        }
        $dql .= $add;
        return $dql;
    }
}
