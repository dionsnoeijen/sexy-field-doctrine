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

use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\Generator\GeneratorInterface;
use Tardigrades\FieldType\ValueObject\Template;
use Tardigrades\FieldType\ValueObject\TemplateDir;
use Tardigrades\SectionField\Generator\Loader\TemplateLoader;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\ValueObject\Handle;
use Tardigrades\SectionField\ValueObject\SectionConfig;

class DoctrineManyToOneGenerator implements GeneratorInterface
{
    const KIND = 'many-to-one';

    public static function generate(FieldInterface $field, TemplateDir $templateDir, ...$options): Template
    {
        $fieldConfig = $field->getConfig()->toArray();

        /** @var SectionManagerInterface $sectionManager */
        $sectionManager = $options[0]['sectionManager'];

        /** @var SectionConfig $sectionConfig */
        $sectionConfig = $options[0]['sectionConfig'];

        $unique = false;
        if (isset($fieldConfig['field']['unique'])) {
            $unique = $fieldConfig['field']['unique'];
        }

        $nullable = true;
        if (isset($fieldConfig['field']['nullable'])) {
            $nullable = $fieldConfig['field']['nullable'];
        }

        if ($fieldConfig['field']['kind'] === self::KIND) {

            $inflector = InflectorFactory::createForLanguage(Language::ENGLISH)->build();

            $handle = $sectionConfig->getHandle();
            $from = $sectionManager->readByHandle($handle);

            /** @var SectionInterface $to */
            $toHandle = $fieldConfig['field']['as'] ?? $fieldConfig['field']['to'];
            $to = $sectionManager->readByHandle(Handle::fromString($fieldConfig['field']['to']));

            $fromVersion = $from->getVersion()->toInt() > 1 ? ('_' . $from->getVersion()->toInt()) : '';
            $toVersion = $to->getVersion()->toInt() > 1 ? ('_' . $to->getVersion()->toInt()) : '';

            if (!empty($fieldConfig['field']['from-handle'])) {
                $fromPluralHandle = $inflector->pluralize($fieldConfig['field']['from-handle']);
            } else {
                $fromPluralHandle = $inflector->pluralize((string)$handle) . $fromVersion;
            }

            return Template::create(
                TemplateLoader::load(
                    (string) $templateDir . '/GeneratorTemplate/doctrine.manytoone.xml.php',
                    [
                        'type' => $fieldConfig['field']['relationship-type'],
                        'toHandle' => $toHandle . $toVersion,
                        'toFullyQualifiedClassName' => $to->getConfig()->getFullyQualifiedClassName(),
                        'fromPluralHandle' => $fromPluralHandle,
                        'cascade' => $fieldConfig['field']['cascade'] ?? false,
                        'unique' => $unique ? 'true' : 'false',
                        'nullable' => $nullable ? 'true' : 'false',
                    ]
                )
            );
        }

        return Template::create('');
    }
}
