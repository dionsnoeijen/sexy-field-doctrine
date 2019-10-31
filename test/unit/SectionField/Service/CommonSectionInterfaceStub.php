<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Service;

use Symfony\Component\Validator\Mapping\ClassMetadata;

class CommonSectionInterfaceStub
{
    public function getId(): ?int
    {
        // TODO: Implement getId() method.
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        // TODO: Implement loadValidatorMetadata() method.
    }

    public function onPrePersist(): void
    {
        // TODO: Implement onPrePersist() method.
    }

    public function onPreUpdate(): void
    {
        // TODO: Implement onPreUpdate() method.
    }

    public function getCreated(): ?\DateTime
    {
        // TODO: Implement getCreated() method.
    }

    public function getUpdated(): ?\DateTime
    {
        // TODO: Implement getUpdated() method.
    }

    public function getSlug(): \Tardigrades\SectionField\ValueObject\Slug
    {
        // TODO: Implement getSlug() method.
    }

    public function getDefault(): string
    {
        // TODO: Implement getDefault() method.
    }

    public static function fieldInfo(): array
    {
        return [];
    }
}
