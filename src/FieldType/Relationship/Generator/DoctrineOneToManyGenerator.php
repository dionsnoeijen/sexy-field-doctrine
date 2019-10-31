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

namespace Tardigrades\FieldType\Relationship\Generator;

use Doctrine\Common\Util\Inflector;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\Generator\GeneratorInterface;
use Tardigrades\FieldType\ValueObject\Template;
use Tardigrades\FieldType\ValueObject\TemplateDir;
use Tardigrades\SectionField\Generator\Loader\TemplateLoader;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\ValueObject\SectionConfig;

class DoctrineOneToManyGenerator implements GeneratorInterface
{
    const KIND = 'one-to-many';

    public static function generate(FieldInterface $field, TemplateDir $templateDir, ...$options): Template
    {
        $fieldConfig = $field->getConfig()->toArray();

        /** @var SectionManagerInterface $sectionManager */
        $sectionManager = $options[0]['sectionManager'];

        /** @var SectionConfig $sectionConfig */
        $sectionConfig = $options[0]['sectionConfig'];

        if ($fieldConfig['field']['kind'] === self::KIND) {
            $handle = $sectionConfig->getHandle();
            $from = $sectionManager->readByHandle($handle);

            /** @var SectionInterface $to */
            $toHandle = $fieldConfig['field']['as'] ?? $fieldConfig['field']['to'];
            $to = $sectionManager->readByHandle(Handle::fromString($fieldConfig['field']['to']));

            $fromVersion = $from->getVersion()->toInt() > 1 ? ('_' . $from->getVersion()->toInt()) : '';
            $toVersion = $to->getVersion()->toInt() > 1 ? ('_' . $to->getVersion()->toInt()) : '';

            $fromHandle = !empty($fieldConfig['field']['from-handle']) ? $fieldConfig['field']['from-handle'] : $handle;

            return Template::create(
                TemplateLoader::load(
                    (string) $templateDir . '/GeneratorTemplate/doctrine.onetomany.xml.php',
                    [
                        'toPluralHandle' => Inflector::pluralize($toHandle) . $toVersion,
                        'toFullyQualifiedClassName' => $to->getConfig()->getFullyQualifiedClassName(),
                        'fromHandle' => (string) $fromHandle, // Don't version this, it's mapped to the entity method.
                        'fromPluralHandle' => Inflector::pluralize((string) $handle) . $fromVersion,
                        'toHandle' => $toHandle . $toVersion,
                        'cascade' => $fieldConfig['field']['cascade'] ?? false
                    ]
                )
            );
        }

        return Template::create('');
    }
}
