<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;

class ManyToMany implements ComponentInterface
{
    const MANY_TO_MANY = 'many-to-many';

    public static function add(
        QueryBuilder $query,
        array $relationship
    ): void {
        $query->join(
            lcfirst($relationship[QueryStructure::FROM]->getClassName()) . '.' . $relationship[QueryStructure::AS],
            lcfirst($relationship[QueryStructure::TO]->getClassName())
        );
    }
}
