<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\Service\ReadOptionsInterface;

class WhereParser
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

    public static function fields(
        ReadOptionsInterface $options
    ): string {
        $wheres = self::conditions($options);
        //$dql = self::toDQL($wheres);
        echo '<pre>';
        //print_r($dql);
        print_r($wheres);
        echo '</pre>';
        exit;
        return $dql;
    }

    private static function conditions(
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
        /**
        WHERE product.productSlug IN(:fields)
        AND NOT accountHasRole_account.firstName = 'anoniem'
        AND (
        participantSession.participantSessionAppointmentDate >= (:laterThan)
        AND participantSession.participantSessionAppointmentDate < (:soonerThan)
        OR (
        (:allowNulls) = TRUE
        AND participantSession.participantSessionAppointmentDate IS NULL
        )
        )
         */
        foreach ($fields as $key=>$field) {
            if (in_array($andOr, self::WHERE_AND_OR)) {
                $conditionToSql = self::conditionToDQL($andOr, $key, $field, $depth);
                $wheres[$key] = $conditionToSql;
                if (in_array($key, self::WHERE_AND_OR)) {
                    $dql .= self::CONVERT_WHERE_AND_OR[$andOr] . ' ';
                }
                foreach ($conditionToSql as $condition) {
                    $dql .= $condition . ' ' . $depth . "\n";
                }
            }
            if (in_array($key, self::WHERE_AND_OR) ||
                !in_array($key, self::COMPARITORS)
            ) {
                if (in_array($key, self::WHERE_AND_OR)) {
                    $wheres[$key] = self::conditions(
                        $options, $field, $key, [], $dql, $depth
                    );
                } else {
                    $depth += 1;
                    $wheres[$key] = self::conditions(
                        $options, $field, $key, $wheres[$key], $dql, $depth
                    );
                }
            }
        }

        echo '---' . "\n\n\n";
        echo $dql;

        return $wheres;
    }

    private static function conditionToDQL(string $andOr, string $handle, array $condition, int $depth): array
    {
        $add = [];

        $path = explode(':', $handle);
        $handle = array_pop($path);
        $fieldPath = implode('_', $path) . '.' . $handle;

        $andOr = self::CONVERT_WHERE_AND_OR[$andOr];
        if ($depth === 0) {
            $andOr = 'WHERE';
        }

        foreach ($condition as $comparitor=>$value) {
            if (in_array($comparitor, self::COMPARITORS)) {
                if (is_null($value)) {
                    if ($comparitor === ReadOptions::EQUAL) {
                        $comparitor = 'IS';
                    } else if ($comparitor === ReadOptions::NOT_EQUAL) {
                        $comparitor = 'IS NOT';
                    }
                    $add[] = "$andOr $fieldPath $comparitor NULL";
                } else if (!is_array($value)) {
                    if ($comparitor === ReadOptions::NOT_EQUAL) {
                        $andOr .= ' NOT';
                    }
                    $add[] = "$andOr $fieldPath = '$value'";
                } else {
                    $value = implode(',', $value);
                    $add[] = "$andOr $fieldPath IN ($value)";
                }
            }
        }
        return $add;
    }
}
