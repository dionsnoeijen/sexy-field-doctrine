<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;

class ManyToOne implements ComponentInterface
{
    const MANY_TO_ONE = 'many-to-one';

    public static function add(
        QueryBuilder $query,
        array $relationship
    ): void {
        $query->leftJoin(
            (string) $relationship[QueryStructure::TO],
            $relationship[QueryStructure::AS],
            'WITH',
            $relationship[QueryStructure::CONDITION]
        );
    }
}
