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

class DoctrineManyToManyGenerator implements GeneratorInterface
{
    const KIND = 'many-to-many';

    public static function generate(FieldInterface $field, TemplateDir $templateDir, ...$options): Template
    {
        $fieldConfig = $field->getConfig()->toArray();

        /** @var SectionManagerInterface $sectionManager */
        $sectionManager = $options[0]['sectionManager'];

        /** @var SectionConfig $sectionConfig */
        $sectionConfig = $options[0]['sectionConfig'];

        if ($fieldConfig['field']['kind'] === self::KIND) {
            $handle = $sectionConfig->getHandle();
            /** @var SectionInterface $from */
            $from = $sectionManager->readByHandle($handle);

            /** @var SectionInterface $to */
            $to = $sectionManager->readByHandle(Handle::fromString($fieldConfig['field']['to']));

            $fromVersion = $from->getVersion()->toInt() > 1 ? ('_' . $from->getVersion()->toInt()) : '';
            $toVersion = $to->getVersion()->toInt() > 1 ? ('_' . $to->getVersion()->toInt()) : '';

            $fromHandle = $fieldConfig['field']['from-handle'] ?? $handle;
            return Template::create(
                TemplateLoader::load(
                    (string)$templateDir . '/GeneratorTemplate/doctrine.manytomany.xml.php',
                    [
                        'type' => $fieldConfig['field']['relationship-type'],
                        'owner' => $fieldConfig['field']['owner'],
                        'toPluralHandle' => Inflector::pluralize(
                            $fieldConfig['field']['as'] ?? $fieldConfig['field']['to']
                        ) . $toVersion,
                        'toFullyQualifiedClassName' => $to
                            ->getConfig()
                            ->getFullyQualifiedClassName(),
                        'fromHandle' => (string)$fromHandle . $fromVersion,
                        'fromPluralHandle' => Inflector::pluralize(
                            (string)$fromHandle
                        ) . $fromVersion,
                        'fromFullyQualifiedClassName' => $sectionConfig
                            ->getFullyQualifiedClassName(),
                        'toHandle' => $fieldConfig['field']['to'] . $toVersion,
                        'cascade' => $fieldConfig['field']['cascade'] ?? false
                    ]
                )
            );
        }

        return Template::create('');
    }
}
