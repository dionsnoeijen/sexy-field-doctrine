<?php

/*
 * This file is part of the SexyField package.
 *
 * (c) Dion Snoeijen <hallo@dionsnoeijen.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types = 1);

namespace Tardigrades\FieldType\Generator;

use Tardigrades\Entity\FieldInterface;
use Tardigrades\FieldType\ValueObject\Template;
use Tardigrades\FieldType\ValueObject\TemplateDir;
use Tardigrades\SectionField\Generator\Loader\TemplateLoader;
use Tardigrades\SectionField\ValueObject\SectionConfig;

class DoctrineFieldGenerator implements GeneratorInterface
{
    private static $DOCTRINE_UNIQUE = 'unique';
    private static $DOCTRINE_NULLABLE = 'nullable';

    public static function generate(FieldInterface $field, TemplateDir $templateDir, ...$options): Template
    {
        $asString = (string) TemplateLoader::load(
            (string) $templateDir
            . '/GeneratorTemplate/doctrine.config.xml.template'
        );

        $asString = str_replace(
            '{{ handle }}',
            $field->getConfig()->getHandle(),
            $asString
        );

        $fieldConfig = $field->getConfig()->toArray();

        $doctrineFields = [
            self::$DOCTRINE_NULLABLE => true,
            self::$DOCTRINE_UNIQUE => false
        ];

        foreach ($doctrineFields as $fieldKey => $value) {
            try {
                $doctrineFields[$fieldKey] = $fieldConfig['field']['generator']['doctrine'][$fieldKey];
            } catch (\Exception $exception) {
                // field not defined
            }

            try {
                /** @var SectionConfig $sectionConfig */
                $sectionConfig = $options[0]['sectionConfig'];
                $generatorConfig = $sectionConfig->getGeneratorConfig()->toArray();

                // If the key exists, it means it's overridden in the section config.
                if (array_key_exists($fieldKey, $generatorConfig['doctrine'][(string)$field->getConfig()->getHandle()])) {
                    $doctrineFields[$fieldKey] = $generatorConfig['doctrine'][(string)$field->getConfig()->getHandle()][$fieldKey];
                }
            } catch (\Throwable $e) {
                // The key did't exist at all
            }

            if (strpos($asString, "{{ $fieldKey }}") !== false) {
                $asString = str_replace(
                    "{{ $fieldKey }}",
                    $doctrineFields[$fieldKey] === true ? 'true' : 'false',
                    $asString
                );
            }
        }

        return Template::create($asString);
    }
}
