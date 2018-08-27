<?php

declare(strict_types=1);

namespace Tardigrades\FieldType\DateTimeTimezone;

/**
 * On determining the structure of the query, there will be a check
 * if this method exists. It will return the fields that are relevant
 * for this field type.
 *
 * Class DateTimeTimezoneQuery
 * @package Tardigrades\FieldType\DateTimeTimezone
 */
class DateTimeTimezoneQuery
{
    public static function select(string $handle): array
    {
        return [
            $handle,
            $handle . 'Timezone'
        ];
    }
}
