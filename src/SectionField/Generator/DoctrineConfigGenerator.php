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

namespace Tardigrades\SectionField\Generator;

use ReflectionClass;
use Tardigrades\Entity\FieldInterface;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\ValueObject\Template;
use Tardigrades\FieldType\ValueObject\TemplateDir;
use Tardigrades\SectionField\Generator\Loader\TemplateLoader;
use Tardigrades\SectionField\Generator\Writer\Writable;
use Tardigrades\SectionField\ValueObject\SectionConfig;

class DoctrineConfigGenerator extends Generator implements GeneratorInterface
{
    /** @var array */
    private $templates;

    /** @var SectionInterface */
    private $section;

    /** @var SectionConfig */
    private $sectionConfig;

    const GENERATE_FOR = 'doctrine';

    public function generateBySection(
        SectionInterface $section
    ): Writable {

        $this->section = $section;
        $this->sectionConfig = $section->getConfig();

        $ignored = false;
        try {
            $ignored = $this->sectionConfig->toArray()['section']['generator']['doctrine']['ignore'];
        } catch (\Throwable $throwable) {
        }
        if ($ignored) {
            throw new IgnoredSectionException();
        }

        $this->initializeTemplates();

        $fields = $this->fieldManager->readByHandles($this->sectionConfig->getFields());

        $this->generateElements($fields);

        return Writable::create(
            (string) $this->generateXml(),
            $this->sectionConfig->getNamespace() . '\\Resources\\config\\doctrine\\',
            ucfirst((string) $this->sectionConfig->getHandle()) . '.orm.xml'
        );
    }

    private function generateElements(array $fields): void
    {
        /** @var FieldInterface $field */
        foreach ($fields as $field) {
            // First see if this field is to be ignored by this generator
            if ($this->shouldIgnore($field)) {
                continue;
            }

            $parsed = $this->getFieldTypeGeneratorConfig($field, self::GENERATE_FOR);

            /**
             * @var string $item
             * @var \Tardigrades\FieldType\Generator\GeneratorInterface $generator
             */
            foreach ($parsed[self::GENERATE_FOR] as $item => $generator) {
                if (!key_exists($item, $this->templates)) {
                    $this->templates[$item] = [];
                }

                if (class_exists($generator)) {
                    $interfaces = class_implements($generator);
                } else {
                    $this->buildMessages[] = 'Generators ' . $generator . ': Generators not found.';
                    break;
                }
                if (key($interfaces) === \Tardigrades\FieldType\Generator\GeneratorInterface::class) {
                    try {
                        $reflector = new ReflectionClass($generator);
                        $method = $reflector->getMethod('generate');

                        $options = [];
                        if (isset($method->getParameters()[1])) {
                            $options = [
                                'sectionManager' => $this->sectionManager,
                                'sectionConfig' => $this->sectionConfig
                            ];
                        }
                        $templateDir = TemplateDir::fromString($this->getFieldTypeTemplateDirectory(
                            $field,
                            'sexy-field-doctrine'
                        ));
                        $this->templates[$item][] = $generator::generate($field, $templateDir, $options);
                    } catch (\Exception $exception) {
                        $this->buildMessages[] = $exception->getMessage();
                    }
                }
            }
        }
    }

    private function initializeTemplates(): void
    {
        $this->templates = [
            'fields' => [],
            'manyToOne' => [],
            'oneToMany' => [],
            'oneToOne' => [],
            'manyToMany' => []
        ];
    }

    private function combine(array $templates): string
    {
        $combined = '';
        foreach ($templates as $template) {
            $combined .= $template;
        }
        return $combined;
    }

    private function generateXml(): Template
    {
        $asString = (string) TemplateLoader::load(__DIR__ . '/GeneratorTemplate/doctrine.config.xml.template');

        foreach ($this->templates as $templateVariable => $templates) {
            $asString = str_replace(
                '{{ ' . $templateVariable . ' }}',
                $this->combine($templates),
                $asString
            );
        }

        $asString = str_replace(
            '{{ fullyQualifiedClassName }}',
            (string) $this->sectionConfig->getFullyQualifiedClassName(),
            $asString
        );

        $tableVersion = $this->section->getVersion()->toInt() > 1 ?
            ('_' . $this->section->getVersion()->toInt()) : '';

        $asString = str_replace(
            '{{ handle }}',
            (string) $this->sectionConfig->getHandle() . $tableVersion,
            $asString
        );

        return Template::create(XmlFormatter::format($asString));
    }
}
