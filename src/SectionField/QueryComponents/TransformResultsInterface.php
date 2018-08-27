<?php
declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

interface TransformResultsInterface
{
    public function intoHierarchy(array $results): array;
}
