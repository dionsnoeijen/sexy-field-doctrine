<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;

interface ComponentInterface
{
    public static function add(
        QueryBuilder $query,
        array $structure
    ): void;
}
