<?php

declare(strict_types=1);

namespace Tardigrades\SectionField\QueryComponents;

use Tardigrades\SectionField\Service\ReadOptionsInterface;
use Tardigrades\SectionField\ValueObject\SectionConfig;

interface QueryStructureInterface
{
    public function get(
        ReadOptionsInterface $readOptions,
        SectionConfig $sectionConfig = null
    ): array;
}
