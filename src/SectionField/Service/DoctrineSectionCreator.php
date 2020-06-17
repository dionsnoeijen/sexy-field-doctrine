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

class DoctrineSectionCreator extends Doctrine implements CreateSectionInterface
{
    public function __construct(
        Registry $registry,
        EntityManagerInterface $entityManager = null
    ) {
        // This allows for unit testing
        $this->entityManager = $entityManager;
        parent::__construct($registry);
    }

    /**
     * @param CommonSectionInterface $data
     * @param OptionsInterface|null $createOptions
     */
    public function save(
        CommonSectionInterface $data,
        ?OptionsInterface $createOptions = null
    ) {
        $this->determineEntityManager(FullyQualifiedClassName::fromString(get_class($data)));

        $this->entityManager->persist($data);
        $this->entityManager->flush();
    }

    /**
     * @param CommonSectionInterface $data
     * @param OptionsInterface|null $createOptions
     */
    public function persist(
        CommonSectionInterface $data,
        ?OptionsInterface $createOptions = null
    ) {
        $this->determineEntityManager(FullyQualifiedClassName::fromString(get_class($data)));

        $this->entityManager->persist($data);
    }

    public function flush(): void
    {
        // This assumes the entity managers has been determined before
        // by calling persist before flushing.
        if (!is_null($this->entityManager)) {
            $this->entityManager->flush();
        }
    }
}
