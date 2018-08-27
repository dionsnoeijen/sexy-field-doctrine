<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;

class OneToOne implements ComponentInterface
{
    const ONE_TO_ONE = 'one-to-one';

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
