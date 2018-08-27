<?php

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\Service\ReadOptions;

class OrderBy implements ComponentInterface
{

    public static function add(
        QueryBuilder $query,
        array $structure
    ): void {
        if ($structure[ReadOptions::ORDER_BY] instanceof OrderBy) {
            $handle = $structure[ReadOptions::ORDER_BY]->getHandle();
            $query->orderBy(
                $handle,
                (string) $structure[ReadOptions::ORDER_BY]->getSort()
            );
        }
    }
}
