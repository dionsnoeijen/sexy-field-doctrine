<?php

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\Service\ReadOptions;
use Tardigrades\SectionField\ValueObject\Offset as OffsetValueObject;

class Offset implements ComponentInterface
{
    public static function add(
        QueryBuilder $query,
        array $structure
    ): void {
        if ($structure[ReadOptions::OFFSET] instanceof OffsetValueObject) {
            $query->setFirstResult($structure[ReadOptions::OFFSET]->toInt());
        }
    }
}
