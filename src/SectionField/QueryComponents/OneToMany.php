<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;

class OneToMany implements ComponentInterface
{
    const ONE_TO_MANY = 'one-to-many';

    public static function add(
        QueryBuilder $query,
        array $relationship
    ): void {
        $query->leftJoin(
            (string) $relationship[QueryStructure::TO],
            $relationship[QueryStructure::AS],
            'WITH',
            !empty($relationship['condition']) ?
                $relationship['condition'] :
                $relationship[QueryStructure::AS] . ' = ' . lcfirst($relationship[QueryStructure::FROM]->getClassName()) . '.' . $relationship[QueryStructure::AS]
        );
    }
}
