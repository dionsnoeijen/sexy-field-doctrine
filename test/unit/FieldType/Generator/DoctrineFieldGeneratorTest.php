<?php
declare (strict_types=1);

namespace Tardigrades\FieldType\Generator;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tardigrades\Entity\Field;
use Tardigrades\FieldType\ValueObject\Template;
use Tardigrades\FieldType\ValueObject\TemplateDir;
use Tardigrades\SectionField\ValueObject\FieldConfig;
use Tardigrades\SectionField\ValueObject\SectionConfig;

/**
 * @coversDefaultClass Tardigrades\FieldType\Generator\DoctrineFieldGenerator
 * @covers ::<private>
 */
final class DoctrineFieldGeneratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private static $FIELD_TYPE_TEMPLATES = [
        'src/FieldType/Birthday',
        'src/FieldType/Boolean',
        'src/FieldType/Choice',
        'src/FieldType/ConfigurationOverride',
        'src/FieldType/Country',
        'src/FieldType/DateTime',
        'src/FieldType/DateTimeTimezone',
        'src/FieldType/Email',
        'src/FieldType/Integer',
        'src/FieldType/Number',
        'src/FieldType/RichTextArea',
        'src/FieldType/Slug',
        'src/FieldType/TextArea',
        'src/FieldType/TextInput',
        'src/FieldType/Uuid',
    ];

    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_all_field_types_without_unique_and_nullable_defined()
    {
        $expectedResults = [
            "src/FieldType/Birthday" => "<field name=\"niets\" nullable=\"true\" type=\"date\" unique=\"false\" />\n",
            "src/FieldType/Boolean" =>  "<field name=\"niets\" type=\"boolean\" unique=\"false\" />\n",
            "src/FieldType/Choice" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/ConfigurationOverride" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/Country" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/DateTime" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"false\"/>\n",
            "src/FieldType/DateTimeTimezone" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"false\"/>
<field name=\"nietsTimezone\" nullable=\"true\" type=\"string\" />\n",
            "src/FieldType/Email" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Integer" =>  "<field name=\"niets\" nullable=\"true\" type=\"integer\" unique=\"false\"/>\n",
            "src/FieldType/Number" =>  "<field name=\"niets\" nullable=\"true\" type=\"float\" unique=\"false\"/>\n",
            "src/FieldType/RichTextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/Slug" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/TextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/TextInput" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Uuid" =>  "<field name=\"niets\" nullable=\"true\" length=\"36\" type=\"string\" unique=\"false\"/>\n",
        ];

        foreach(self::$FIELD_TYPE_TEMPLATES as $fieldTemplate) {
            $mockedFieldInterface = Mockery::mock(new Field())->makePartial();
            $templateDir = TemplateDir::fromString($fieldTemplate);

            $mockedFieldInterface->shouldReceive('getConfig')
                ->andReturn(
                    FieldConfig::fromArray(
                        [
                            'field' => [
                                'name' => 'iets',
                                'handle' => 'niets',
                                'kind' => '12345',
                                'entityEvents' => ['1', '2']
                            ]
                        ]
                    )
                );

            /** @var Template $generatedTemplate */
            $generatedTemplate = DoctrineFieldGenerator::generate($mockedFieldInterface, $templateDir);
            $this->assertInstanceOf(Template::class, $generatedTemplate);
            $this->assertNotEmpty($generatedTemplate);

            $this->assertEquals($expectedResults[$fieldTemplate], (string) $generatedTemplate);
        }
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_all_field_types_with_nullable_set_to_false()
    {
        $expectedResults = [
            "src/FieldType/Birthday" => "<field name=\"niets\" nullable=\"false\" type=\"date\" unique=\"false\" />\n",
            "src/FieldType/Boolean" =>  "<field name=\"niets\" type=\"boolean\" unique=\"false\" />\n",
            "src/FieldType/Choice" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/ConfigurationOverride" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/Country" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/DateTime" =>  "<field name=\"niets\" nullable=\"false\" type=\"datetime\" unique=\"false\"/>\n",
            "src/FieldType/DateTimeTimezone" =>  "<field name=\"niets\" nullable=\"false\" type=\"datetime\" unique=\"false\"/>
<field name=\"nietsTimezone\" nullable=\"false\" type=\"string\" />\n",
            "src/FieldType/Email" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Integer" =>  "<field name=\"niets\" nullable=\"false\" type=\"integer\" unique=\"false\"/>\n",
            "src/FieldType/Number" =>  "<field name=\"niets\" nullable=\"false\" type=\"float\" unique=\"false\"/>\n",
            "src/FieldType/RichTextArea" =>  "<field name=\"niets\" nullable=\"false\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/Slug" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/TextArea" =>  "<field name=\"niets\" nullable=\"false\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/TextInput" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Uuid" =>  "<field name=\"niets\" nullable=\"false\" length=\"36\" type=\"string\" unique=\"false\"/>\n",
        ];

        foreach(self::$FIELD_TYPE_TEMPLATES as $fieldTemplate) {
            $mockedFieldInterface = Mockery::mock(new Field())->makePartial();
            $templateDir = TemplateDir::fromString($fieldTemplate);

            $mockedFieldInterface->shouldReceive('getConfig')
                ->andReturn(
                    FieldConfig::fromArray(
                        [
                            'field' => [
                                'name' => 'iets',
                                'handle' => 'niets',
                                'kind' => '12345',
                                'entityEvents' => ['1', '2'],
                                'generator' => [
                                    'doctrine' => [
                                        'nullable' => false
                                    ]
                                ]
                            ]
                        ]
                    )
                );

            /** @var Template $generatedTemplate */
            $generatedTemplate = DoctrineFieldGenerator::generate($mockedFieldInterface, $templateDir);
            $this->assertInstanceOf(Template::class, $generatedTemplate);
            $this->assertNotEmpty($generatedTemplate);

            $this->assertEquals($expectedResults[$fieldTemplate], (string) $generatedTemplate);
        }
    }


    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_all_field_types_with_nullable_set_to_true()
    {
        $expectedResults = [
            "src/FieldType/Birthday" => "<field name=\"niets\" nullable=\"true\" type=\"date\" unique=\"false\" />\n",
            "src/FieldType/Boolean" =>  "<field name=\"niets\" type=\"boolean\" unique=\"false\" />\n",
            "src/FieldType/Choice" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/ConfigurationOverride" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/Country" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/DateTime" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"false\"/>\n",
            "src/FieldType/DateTimeTimezone" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"false\"/>
<field name=\"nietsTimezone\" nullable=\"true\" type=\"string\" />\n",
            "src/FieldType/Email" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Integer" =>  "<field name=\"niets\" nullable=\"true\" type=\"integer\" unique=\"false\"/>\n",
            "src/FieldType/Number" =>  "<field name=\"niets\" nullable=\"true\" type=\"float\" unique=\"false\"/>\n",
            "src/FieldType/RichTextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/Slug" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/TextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/TextInput" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Uuid" =>  "<field name=\"niets\" nullable=\"true\" length=\"36\" type=\"string\" unique=\"false\"/>\n",
        ];

        foreach(self::$FIELD_TYPE_TEMPLATES as $fieldTemplate) {
            $mockedFieldInterface = Mockery::mock(new Field())->makePartial();
            $templateDir = TemplateDir::fromString($fieldTemplate);

            $mockedFieldInterface->shouldReceive('getConfig')
                ->andReturn(
                    FieldConfig::fromArray(
                        [
                            'field' => [
                                'name' => 'iets',
                                'handle' => 'niets',
                                'kind' => '12345',
                                'entityEvents' => ['1', '2'],
                                'generator' => [
                                    'doctrine' => [
                                        'nullable' => true
                                    ]
                                ]
                            ]
                        ]
                    )
                );

            /** @var Template $generatedTemplate */
            $generatedTemplate = DoctrineFieldGenerator::generate($mockedFieldInterface, $templateDir);
            $this->assertInstanceOf(Template::class, $generatedTemplate);
            $this->assertNotEmpty($generatedTemplate);

            $this->assertEquals($expectedResults[$fieldTemplate], (string) $generatedTemplate);
        }
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_all_field_types_with_unique_set_to_false()
    {
        $expectedResults = [
            "src/FieldType/Birthday" => "<field name=\"niets\" nullable=\"false\" type=\"date\" unique=\"false\" />\n",
            "src/FieldType/Boolean" =>  "<field name=\"niets\" type=\"boolean\" unique=\"false\" />\n",
            "src/FieldType/Choice" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/ConfigurationOverride" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/Country" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\" />\n",
            "src/FieldType/DateTime" =>  "<field name=\"niets\" nullable=\"false\" type=\"datetime\" unique=\"false\"/>\n",
            "src/FieldType/DateTimeTimezone" =>  "<field name=\"niets\" nullable=\"false\" type=\"datetime\" unique=\"false\"/>
<field name=\"nietsTimezone\" nullable=\"false\" type=\"string\" />\n",
            "src/FieldType/Email" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Integer" =>  "<field name=\"niets\" nullable=\"false\" type=\"integer\" unique=\"false\"/>\n",
            "src/FieldType/Number" =>  "<field name=\"niets\" nullable=\"false\" type=\"float\" unique=\"false\"/>\n",
            "src/FieldType/RichTextArea" =>  "<field name=\"niets\" nullable=\"false\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/Slug" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/TextArea" =>  "<field name=\"niets\" nullable=\"false\" type=\"text\" unique=\"false\"/>\n",
            "src/FieldType/TextInput" =>  "<field name=\"niets\" nullable=\"false\" type=\"string\" unique=\"false\"/>\n",
            "src/FieldType/Uuid" =>  "<field name=\"niets\" nullable=\"false\" length=\"36\" type=\"string\" unique=\"false\"/>\n",
        ];

        foreach(self::$FIELD_TYPE_TEMPLATES as $fieldTemplate) {
            $mockedFieldInterface = Mockery::mock(new Field())->makePartial();
            $templateDir = TemplateDir::fromString($fieldTemplate);

            $mockedFieldInterface->shouldReceive('getConfig')
                ->andReturn(
                    FieldConfig::fromArray(
                        [
                            'field' => [
                                'name' => 'iets',
                                'handle' => 'niets',
                                'kind' => '12345',
                                'entityEvents' => ['1', '2'],
                                'generator' => [
                                    'doctrine' => [
                                        'nullable' => false,
                                        'unique' => false
                                    ]
                                ]
                            ]
                        ]
                    )
                );

            /** @var Template $generatedTemplate */
            $generatedTemplate = DoctrineFieldGenerator::generate($mockedFieldInterface, $templateDir);
            $this->assertInstanceOf(Template::class, $generatedTemplate);
            $this->assertNotEmpty($generatedTemplate);

            $this->assertEquals($expectedResults[$fieldTemplate], (string) $generatedTemplate);
        }
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_all_field_types_with_unique_set_to_true()
    {
        $expectedResults = [
            "src/FieldType/Birthday" => "<field name=\"niets\" nullable=\"true\" type=\"date\" unique=\"true\" />\n",
            "src/FieldType/Boolean" =>  "<field name=\"niets\" type=\"boolean\" unique=\"true\" />\n",
            "src/FieldType/Choice" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/ConfigurationOverride" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/Country" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/DateTime" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"true\"/>\n",
            "src/FieldType/DateTimeTimezone" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"true\"/>
<field name=\"nietsTimezone\" nullable=\"true\" type=\"string\" />\n",
            "src/FieldType/Email" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\"/>\n",
            "src/FieldType/Integer" =>  "<field name=\"niets\" nullable=\"true\" type=\"integer\" unique=\"true\"/>\n",
            "src/FieldType/Number" =>  "<field name=\"niets\" nullable=\"true\" type=\"float\" unique=\"true\"/>\n",
            "src/FieldType/RichTextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"true\"/>\n",
            "src/FieldType/Slug" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/TextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"true\"/>\n",
            "src/FieldType/TextInput" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\"/>\n",
            "src/FieldType/Uuid" =>  "<field name=\"niets\" nullable=\"true\" length=\"36\" type=\"string\" unique=\"true\"/>\n",
        ];

        foreach(self::$FIELD_TYPE_TEMPLATES as $fieldTemplate) {
            $mockedFieldInterface = Mockery::mock(new Field())->makePartial();
            $templateDir = TemplateDir::fromString($fieldTemplate);

            $mockedFieldInterface->shouldReceive('getConfig')
                ->andReturn(
                    FieldConfig::fromArray(
                        [
                            'field' => [
                                'name' => 'iets',
                                'handle' => 'niets',
                                'kind' => '12345',
                                'entityEvents' => ['1', '2'],
                                'generator' => [
                                    'doctrine' => [
                                        'nullable' => true,
                                        'unique' => true
                                    ]
                                ]
                            ]
                        ]
                    )
                );

            /** @var Template $generatedTemplate */
            $generatedTemplate = DoctrineFieldGenerator::generate($mockedFieldInterface, $templateDir);
            $this->assertInstanceOf(Template::class, $generatedTemplate);
            $this->assertNotEmpty($generatedTemplate);

            $this->assertEquals($expectedResults[$fieldTemplate], (string) $generatedTemplate);
        }
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_all_field_types_and_override_nullable_and_unique_in_section_config()
    {
        $expectedResults = [
            "src/FieldType/Birthday" => "<field name=\"niets\" nullable=\"true\" type=\"date\" unique=\"true\" />\n",
            "src/FieldType/Boolean" =>  "<field name=\"niets\" type=\"boolean\" unique=\"true\" />\n",
            "src/FieldType/Choice" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/ConfigurationOverride" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/Country" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/DateTime" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"true\"/>\n",
            "src/FieldType/DateTimeTimezone" =>  "<field name=\"niets\" nullable=\"true\" type=\"datetime\" unique=\"true\"/>
<field name=\"nietsTimezone\" nullable=\"true\" type=\"string\" />\n",
            "src/FieldType/Email" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\"/>\n",
            "src/FieldType/Integer" =>  "<field name=\"niets\" nullable=\"true\" type=\"integer\" unique=\"true\"/>\n",
            "src/FieldType/Number" =>  "<field name=\"niets\" nullable=\"true\" type=\"float\" unique=\"true\"/>\n",
            "src/FieldType/RichTextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"true\"/>\n",
            "src/FieldType/Slug" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\" />\n",
            "src/FieldType/TextArea" =>  "<field name=\"niets\" nullable=\"true\" type=\"text\" unique=\"true\"/>\n",
            "src/FieldType/TextInput" =>  "<field name=\"niets\" nullable=\"true\" type=\"string\" unique=\"true\"/>\n",
            "src/FieldType/Uuid" =>  "<field name=\"niets\" nullable=\"true\" length=\"36\" type=\"string\" unique=\"true\"/>\n",
        ];

        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => 'section_name',
                'handle' => 'section_handle',
                'fields' => [
                    'niets'
                ],
                'slug' => ['title'],
                'default' => 'title',
                'namespace' => 'My\\Namespace',
                'generator' => [
                    'doctrine' => [
                        'niets' => [
                            'nullable' => true,
                            'unique' => true
                        ]
                    ]
                ]
            ]
        ]);

        foreach(self::$FIELD_TYPE_TEMPLATES as $fieldTemplate) {
            $mockedFieldInterface = Mockery::mock(new Field())->makePartial();
            $templateDir = TemplateDir::fromString($fieldTemplate);

            $mockedFieldInterface->shouldReceive('getConfig')
                ->andReturn(
                    FieldConfig::fromArray(
                        [
                            'field' => [
                                'name' => 'iets',
                                'handle' => 'niets',
                                'kind' => '12345',
                                'entityEvents' => ['1', '2'],
                                'generator' => [
                                    'doctrine' => [
                                        'nullable' => false,
                                        'unique' => false
                                    ]
                                ]
                            ]
                        ]
                    )
                );

            /** @var Template $generatedTemplate */
            $generatedTemplate = DoctrineFieldGenerator::generate($mockedFieldInterface, $templateDir, [ 'sectionConfig' => $sectionConfig]);
            $this->assertInstanceOf(Template::class, $generatedTemplate);
            $this->assertNotEmpty($generatedTemplate);

            $this->assertEquals($expectedResults[$fieldTemplate], (string) $generatedTemplate);
        }
    }
}
