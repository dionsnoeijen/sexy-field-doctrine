<?php
declare (strict_types=1);

namespace Tardigrades\FieldType\Relationship\Generator;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tardigrades\Entity\Field;
use Tardigrades\Entity\SectionInterface;
use Tardigrades\FieldType\ValueObject\Template;
use Tardigrades\FieldType\ValueObject\TemplateDir;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\ValueObject\FieldConfig;
use Tardigrades\SectionField\ValueObject\SectionConfig;
use Tardigrades\SectionField\ValueObject\Version;

/**
 * @coversDefaultClass Tardigrades\FieldType\Relationship\Generator\DoctrineManyToOneGenerator
 * @covers ::<private>
 */
class DoctrineManyToOneGeneratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_an_empty_template_with_wrong_kind()
    {
        $field = new Field();
        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'niets',
                    'kind' => 'wrong kind'
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
        $generated = DoctrineManyToOneGenerator::generate(
            $field,
            TemplateDir::fromString('src/FieldType/Relationship'),
            $options
        );
        $this->assertEquals(Template::create(''), $generated);
    }

    /**
     * @test
     * @covers ::generate
     */
    public function it_generates_a_proper_template_too()
    {
        $fieldArrayThing = [
            'field' =>
                [
                    'name' => 'iets',
                    'handle' => 'some handle',
                    'kind' => DoctrineManyToOneGenerator::KIND,
                    'from' => 'this',
                    'to' => 'that',
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
            ->andReturn(Version::fromInt(123));

        $toSectionConfig =
            SectionConfig::fromArray(
                [
                    'section' => [
                        'name' => 'nameTo',
                        'handle' => 'ToBeMapped',
                        'fields' => ['a', 'b'],
                        'default' => 'default',
                        'namespace' => 'nameFromSpace'
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
                    'handle' => 'mapper',
                    'fields' => ['a', 'v', 'b'],
                    'default' => 'def',
                    'namespace' => 'nameInSpace'
                ]
            ])
        ];

        $generated = DoctrineManyToOneGenerator::generate(
            $field,
            TemplateDir::fromString('src/FieldType/Relationship'),
            $options
        );
        $this->assertNotEmpty($generated);
        $this->assertInstanceOf(Template::class, $generated);

        $expected = <<<EOT
<many-to-one field="that_123" target-entity="nameFromSpace\Entity\ToBeMapped" inversed-by="mappers_37">
    <cascade>
        <cascade-all/>
    </cascade>
    <join-column name="that_123_id" referenced-column-name="id" />
</many-to-one>

EOT;

        $this->assertEquals($expected, (string)$generated);
    }
}
