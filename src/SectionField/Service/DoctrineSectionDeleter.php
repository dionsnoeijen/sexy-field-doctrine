<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Tardigrades\SectionField\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use Tardigrades\SectionField\Generator\CommonSectionInterface;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;

class DoctrineSectionDeleter extends Doctrine implements DeleteSectionInterface
{
    public function __construct(
        Registry $registry,
        EntityManagerInterface $entityManager = null
    ) {
        $this->entityManager = $entityManager;
        parent::__construct($registry);
    }

    /**
     * @param CommonSectionInterface $sectionEntryEntity
     * @param OptionsInterface|null $deleteOptions
     * @return bool
     */
    public function delete(
        CommonSectionInterface $sectionEntryEntity,
        ?OptionsInterface $deleteOptions = null
    ): bool {
        $this->determineEntityManager(
            FullyQualifiedClassName::fromString(get_class($sectionEntryEntity))
        );
        try {
            $this->entityManager->remove($sectionEntryEntity);
            $this->entityManager->flush();
        } catch (\Exception $exception) {
            return false;
        }
        return true;
    }

    /**
     * @param CommonSectionInterface $sectionEntryEntity
     * @throws NoEntityManagerFoundForSection
     */
    public function remove(CommonSectionInterface $sectionEntryEntity): void
    {
        $this->determineEntityManager(
            FullyQualifiedClassName::fromString(get_class($sectionEntryEntity))
        );
        $this->entityManager->remove($sectionEntryEntity);
    }

    public function flush(): void
    {
        // This assumes the entity managers has been determined before
        // by calling remove before flushing.
        if (!is_null($this->entityManager)) {
            $this->entityManager->flush();
        }
    }
}
