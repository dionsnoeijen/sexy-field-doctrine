<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Doctrine\ORM\QueryBuilder;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;

class Where implements ComponentInterface
{
    public static function add(
        QueryBuilder $query,
        array $structure
    ): void {
        if ($structure['where']) {
            foreach ($structure['where'] as $where) {
                if (!is_array($where['parameters']['value'])) {
                    $query->andWhere(
                        $where['parameters']['property'] . ' = :' .
                        $where['parameters']['key']
                    );
                    $query->setParameter($where['parameters']['key'], $where['parameters']['value']);
                } else {
                    $query->andWhere(
                        $query->expr()->in(
                            $where['parameters']['property'],
                            ':' . $where['parameters']['key']
                        )
                    );
                    $query->setParameter($where['parameters']['key'], $where['parameters']['value']);
                }
            }
        }
    }
}
