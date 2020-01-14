<?php
declare (strict_types=1);

namespace Tardigrades\Fieldtype\Relationship\Generator;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tardigrades\Entity\Field;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\ValueObject\Template;
use Tardigrades\FieldType\ValueObject\TemplateDir;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\ValueObject\FieldConfig;
use Tardigrades\SectionField\ValueObject\SectionConfig;
use Tardigrades\SectionField\ValueObject\Version;

/**
 * @coversDefaultClass Tardigrades\FieldType\Relationship\Generator\DoctrineOneToOneGenerator
 * @covers ::<private>
 */
final class DoctrineOneToOneGeneratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @test
     * @covers ::generate
     */
    public function it_should_generate_with_wrong_kind()
    {
        $field = new Field();

        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'niets',
                    'kind' => 'wrong kind',
                    'relationship-type' => 'bidirectional',
                    'owner' => true
                ]
        ];
        $field = $field->setConfig($fieldArrayThing);

        $options = [
            'sectionManager' => [
                'handle' => 'what'
            ],
            'sectionConfig' => [
                'field' => [
                    'name' => 'iets',
                    'handle' => 'niets'
                ]
            ]
        ];

        $generated = DoctrineOneToOneGenerator::generate(
            $field,
            TemplateDir::fromString(''),
            $options
        );

        $this->assertEquals(Template::create(''), $generated);
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_should_generate_with_bidirectional_and_owner()
    {
        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'some handle',
                    'kind' => DoctrineOneToOneGenerator::KIND,
                    'relationship-type' => 'bidirectional',
                    'owner' => true,
                    'from' => 'me',
                    'to' => 'you',
                    'type' => 'not my type',
                    'cascade' => 'persist'
                ]
        ];
        $fieldConfig = FieldConfig::fromArray($fieldArrayThing);

        $field = Mockery::mock(new Field())
            ->shouldDeferMissing()
            ->shouldReceive('getConfig')
            ->andReturn($fieldConfig)
            ->getMock();

        $doctrineSectionManager = Mockery::mock(SectionManagerInterface::class);

        $fromSectionInterface = Mockery::mock(SectionInterface::class);
        $toSectionInterface = Mockery::mock(SectionInterface::class);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($fromSectionInterface);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($toSectionInterface);

        $fromSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(37));

        $toSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(333));

        $toSectionConfig =
        SectionConfig::fromArray(
            [
                'section' => [
                    'name' => 'nameTo',
                    'handle' => 'handle',
                    'fields' => ['a' => 'b'],
                    'default' => 'default',
                    'namespace' => 'namespace'
                ]
            ]
        );

        $toSectionInterface->shouldReceive('getConfig')
            ->once()
            ->andReturn($toSectionConfig);

        $options = [
            'sectionManager' => $doctrineSectionManager,
            'sectionConfig' => SectionConfig::fromArray([
                'section' => [
                    'name' => 'iets',
                    'handle' => 'niets',
                    'fields' => ['a' => 'v'],
                    'default' => 'def',
                    'namespace' => 'nameInSpace'
                ]
            ])
        ];

        $generated = DoctrineOneToOneGenerator::generate(
            $field,
            TemplateDir::fromString(__DIR__ . '/../../../../../src/FieldType/Relationship'),
            $options
        );

        $this->assertInstanceOf(Template::class, $generated);

        $expected = <<<EOT
<one-to-one field="you_333" target-entity="namespace\Entity\Handle" inversed-by="niets_37">
    <cascade>
        <cascade-persist />
    </cascade>
    <join-column name="you_333_id" referenced-column-name="id" nullable="true" unique="false" />
</one-to-one>

EOT;

        $this->assertEquals($expected, (string)$generated);
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_should_generate_with_bidirectional_and_not_owner()
    {
        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'some handle',
                    'kind' => DoctrineOneToOneGenerator::KIND,
                    'relationship-type' => 'bidirectional',
                    'owner' => false,
                    'from' => 'me',
                    'to' => 'you',
                    'type' => 'not my type'
                ]
        ];
        $fieldConfig = FieldConfig::fromArray($fieldArrayThing);

        $field = Mockery::mock(new Field())
            ->shouldDeferMissing()
            ->shouldReceive('getConfig')
            ->andReturn($fieldConfig)
            ->getMock();

        $doctrineSectionManager = Mockery::mock(SectionManagerInterface::class);

        $fromSectionInterface = Mockery::mock(SectionInterface::class);
        $toSectionInterface = Mockery::mock(SectionInterface::class);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($fromSectionInterface);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($toSectionInterface);

        $fromSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(37));

        $toSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(333));

        $toSectionConfig =
            SectionConfig::fromArray(
                [
                    'section' => [
                        'name' => 'nameTo',
                        'handle' => 'handle',
                        'fields' => ['a' => 'b'],
                        'default' => 'default',
                        'namespace' => 'namespace'
                    ]
                ]
            );

        $toSectionInterface->shouldReceive('getConfig')
            ->once()
            ->andReturn($toSectionConfig);

        $options = [
            'sectionManager' => $doctrineSectionManager,
            'sectionConfig' => SectionConfig::fromArray([
                'section' => [
                    'name' => 'iets',
                    'handle' => 'niets',
                    'fields' => ['a' => 'v'],
                    'default' => 'def',
                    'namespace' => 'nameInSpace'
                ]
            ])
        ];

        $generated = DoctrineOneToOneGenerator::generate(
            $field,
            TemplateDir::fromString(__DIR__ . '/../../../../../src/FieldType/Relationship'),
            $options
        );

        $this->assertInstanceOf(Template::class, $generated);

        $expected = <<<EOT
<one-to-one field="you_333" target-entity="namespace\Entity\Handle" mapped-by="niets_37">
</one-to-one>

EOT;

        $this->assertEquals($expected, (string)$generated);
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_should_generate_with_unidirectional()
    {
        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'some handle',
                    'kind' => DoctrineOneToOneGenerator::KIND,
                    'relationship-type' => 'unidirectional',
                    'owner' => true,
                    'from' => 'me',
                    'to' => 'you',
                    'type' => 'not my type'
                ]
        ];
        $fieldConfig = FieldConfig::fromArray($fieldArrayThing);

        $field = Mockery::mock(new Field())
            ->shouldDeferMissing()
            ->shouldReceive('getConfig')
            ->andReturn($fieldConfig)
            ->getMock();

        $doctrineSectionManager = Mockery::mock(SectionManagerInterface::class);

        $fromSectionInterface = Mockery::mock(SectionInterface::class);
        $toSectionInterface = Mockery::mock(SectionInterface::class);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($fromSectionInterface);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($toSectionInterface);

        $fromSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(37));

        $toSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(333));

        $toSectionConfig =
            SectionConfig::fromArray(
                [
                    'section' => [
                        'name' => 'nameTo',
                        'handle' => 'handle',
                        'fields' => ['a' => 'b'],
                        'default' => 'default',
                        'namespace' => 'namespace'
                    ]
                ]
            );

        $toSectionInterface->shouldReceive('getConfig')
            ->once()
            ->andReturn($toSectionConfig);

        $options = [
            'sectionManager' => $doctrineSectionManager,
            'sectionConfig' => SectionConfig::fromArray([
                'section' => [
                    'name' => 'iets',
                    'handle' => 'niets',
                    'fields' => ['a' => 'v'],
                    'default' => 'def',
                    'namespace' => 'nameInSpace'
                ]
            ])
        ];

        $generated = DoctrineOneToOneGenerator::generate(
            $field,
            TemplateDir::fromString(__DIR__ . '/../../../../../src/FieldType/Relationship'),
            $options
        );

        $this->assertInstanceOf(Template::class, $generated);

        $expected = <<<EOT
<one-to-one field="you_333" target-entity="namespace\Entity\Handle">
    <join-column name="you_333_id" referenced-column-name="id" nullable="true" unique="false" />
</one-to-one>

EOT;

        $this->assertEquals($expected, (string)$generated);
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_should_generate_with_nullable_and_unique_true()
    {
        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'some handle',
                    'kind' => DoctrineOneToOneGenerator::KIND,
                    'relationship-type' => 'unidirectional',
                    'owner' => true,
                    'from' => 'me',
                    'to' => 'you',
                    'type' => 'not my type',
                    'nullable' => true,
                    'unique' => true
                ]
        ];
        $fieldConfig = FieldConfig::fromArray($fieldArrayThing);

        $field = Mockery::mock(new Field())
            ->shouldDeferMissing()
            ->shouldReceive('getConfig')
            ->andReturn($fieldConfig)
            ->getMock();

        $doctrineSectionManager = Mockery::mock(SectionManagerInterface::class);

        $fromSectionInterface = Mockery::mock(SectionInterface::class);
        $toSectionInterface = Mockery::mock(SectionInterface::class);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($fromSectionInterface);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($toSectionInterface);

        $fromSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(37));

        $toSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(333));

        $toSectionConfig =
            SectionConfig::fromArray(
                [
                    'section' => [
                        'name' => 'nameTo',
                        'handle' => 'handle',
                        'fields' => ['a' => 'b'],
                        'default' => 'default',
                        'namespace' => 'namespace'
                    ]
                ]
            );

        $toSectionInterface->shouldReceive('getConfig')
            ->once()
            ->andReturn($toSectionConfig);

        $options = [
            'sectionManager' => $doctrineSectionManager,
            'sectionConfig' => SectionConfig::fromArray([
                'section' => [
                    'name' => 'iets',
                    'handle' => 'niets',
                    'fields' => ['a' => 'v'],
                    'default' => 'def',
                    'namespace' => 'nameInSpace'
                ]
            ])
        ];

        $generated = DoctrineOneToOneGenerator::generate(
            $field,
            TemplateDir::fromString(__DIR__ . '/../../../../../src/FieldType/Relationship'),
            $options
        );

        $this->assertInstanceOf(Template::class, $generated);

        $expected = <<<EOT
<one-to-one field="you_333" target-entity="namespace\Entity\Handle">
    <join-column name="you_333_id" referenced-column-name="id" nullable="true" unique="true" />
</one-to-one>

EOT;

        $this->assertEquals($expected, (string)$generated);
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_should_generate_with_nullable_and_unique_false()
    {
        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'some handle',
                    'kind' => DoctrineOneToOneGenerator::KIND,
                    'relationship-type' => 'unidirectional',
                    'owner' => true,
                    'from' => 'me',
                    'to' => 'you',
                    'type' => 'not my type',
                    'nullable' => false,
                    'unique' => false
                ]
        ];
        $fieldConfig = FieldConfig::fromArray($fieldArrayThing);

        $field = Mockery::mock(new Field())
            ->shouldDeferMissing()
            ->shouldReceive('getConfig')
            ->andReturn($fieldConfig)
            ->getMock();

        $doctrineSectionManager = Mockery::mock(SectionManagerInterface::class);

        $fromSectionInterface = Mockery::mock(SectionInterface::class);
        $toSectionInterface = Mockery::mock(SectionInterface::class);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($fromSectionInterface);

        $doctrineSectionManager->shouldReceive('readByHandle')
            ->once()
            ->andReturn($toSectionInterface);

        $fromSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(37));

        $toSectionInterface->shouldReceive('getVersion')
            ->twice()
            ->andReturn(Version::fromInt(333));

        $toSectionConfig =
            SectionConfig::fromArray(
                [
                    'section' => [
                        'name' => 'nameTo',
                        'handle' => 'handle',
                        'fields' => ['a' => 'b'],
                        'default' => 'default',
                        'namespace' => 'namespace'
                    ]
                ]
            );

        $toSectionInterface->shouldReceive('getConfig')
            ->once()
            ->andReturn($toSectionConfig);

        $options = [
            'sectionManager' => $doctrineSectionManager,
            'sectionConfig' => SectionConfig::fromArray([
                'section' => [
                    'name' => 'iets',
                    'handle' => 'niets',
                    'fields' => ['a' => 'v'],
                    'default' => 'def',
                    'namespace' => 'nameInSpace'
                ]
            ])
        ];

        $generated = DoctrineOneToOneGenerator::generate(
            $field,
            TemplateDir::fromString(__DIR__ . '/../../../../../src/FieldType/Relationship'),
            $options
        );

        $this->assertInstanceOf(Template::class, $generated);

        $expected = <<<EOT
<one-to-one field="you_333" target-entity="namespace\Entity\Handle">
    <join-column name="you_333_id" referenced-column-name="id" nullable="false" unique="false" />
</one-to-one>

EOT;

        $this->assertEquals($expected, (string)$generated);
    }
}
