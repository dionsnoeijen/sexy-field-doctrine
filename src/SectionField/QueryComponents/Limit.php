<?php

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\ValueObject\Limit as LimitValueObject;

class Limit implements ComponentInterface
{
    public static function add(
        QueryBuilder $query,
        array $structure
    ): void {
        if ($structure[ReadOptions::LIMIT] instanceof LimitValueObject) {
            $query->setMaxResults($structure[ReadOptions::LIMIT]->toInt());
        }
    }
}
