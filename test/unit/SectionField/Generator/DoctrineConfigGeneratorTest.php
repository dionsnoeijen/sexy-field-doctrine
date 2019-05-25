<?php
declare (strict_types=1);

namespace Tardigrades\SectionField\Generator;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Tardigrades\Entity\Field;
use Tardigrades\Entity\FieldType;
use Tardigrades\Entity\FieldTypeInterface;
use Tardigrades\Entity\Section;
use Tardigrades\FieldType\Generator\DoctrineFieldGenerator;
use Tardigrades\FieldType\Relationship\Generator\DoctrineOneToOneGenerator;
use Tardigrades\SectionField\Generator\Writer\Writable;
use Tardigrades\SectionField\Service\FieldManagerInterface;
use Tardigrades\SectionField\Service\FieldTypeManagerInterface;
use Tardigrades\SectionField\Service\SectionManagerInterface;
use Tardigrades\SectionField\ValueObject\FieldConfig;
use Tardigrades\SectionField\ValueObject\FieldTypeGeneratorConfig;
use Tardigrades\SectionField\ValueObject\FullyQualifiedClassName;
use Tardigrades\SectionField\ValueObject\SectionConfig;
use Tardigrades\SectionField\ValueObject\Type;

/**
 * @coversDefaultClass \Tardigrades\SectionField\Generator\DoctrineConfigGenerator
 * @covers ::<<private>>
 * @covers ::__construct
 */
final class DoctrineConfigGeneratorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var FieldManagerInterface|Mockery\Mock */
    private $fieldManager;

    /** @var FieldTypeManagerInterface|Mockery\Mock */
    private $fieldTypeManager;

    /** @var SectionManagerInterface|Mockery\Mock */
    private $sectionManager;

    /** @var ContainerInterface|Mockery\Mock */
    private $container;

    /** @var DoctrineConfigGenerator */
    private $generator;

    public function setUp()
    {
        $this->fieldManager = Mockery::mock(FieldManagerInterface::class);
        $this->fieldTypeManager = Mockery::mock(FieldTypeManagerInterface::class);
        $this->sectionManager = Mockery::mock(SectionManagerInterface::class);
        $this->container = Mockery::mock(ContainerInterface::class);
        $this->generator = new DoctrineConfigGenerator(
            $this->fieldManager,
            $this->fieldTypeManager,
            $this->sectionManager,
            $this->container
        );
    }

    /**
     * @test
     * @covers ::generateBySection
     */
    public function it_should_generate_by_a_section_from_template()
    {
        $sectionOne = $this->givenASectionWithName('One');

        $fieldtypeConfigDoctrine = FieldTypeGeneratorConfig::fromArray(
            [
                'doctrine' => [
                    'oneToOne' => DoctrineOneToOneGenerator::class
                ]
            ]
        );

        $fieldType = Mockery::mock(FieldTypeInterface::class);
        $fieldType->shouldReceive('getFieldTypeGeneratorConfig')
            ->twice()
            ->andReturn($fieldtypeConfigDoctrine);
        $fieldType->shouldReceive('getFullyQualifiedClassName')
            ->andReturn(FullyQualifiedClassName::fromString('\My\Namespace\Field'));
        $fieldType->shouldReceive('getType')->andReturn(Type::fromString('EmailType'));
        $fieldType->shouldReceive('directory')->andReturn(__DIR__ . '/../../../../src/FieldType/Relationship');

        $fieldOne = $this->givenARelationshipFieldWithNameKindAndTo('One', 'one-to-one', 'Two');

        $this->fieldManager->shouldReceive('readByHandles')
            ->once()
            ->andReturn([$fieldOne]);

        $this->sectionManager->shouldReceive('readByHandle')
            ->twice()
            ->andReturn($sectionOne);

        $this->container->shouldReceive('get')
            ->times(3)
            ->andReturn($fieldType);

        $writable = $this->generator->generateBySection($sectionOne);

        //@codingStandardsIgnoreStart
        $expected = <<<'EOT'
<?xml version="1.0"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping http://raw.github.com/doctrine/doctrine2/master/doctrine-mapping.xsd">
  <entity name="My\Namespace\Entity\SectionOne" table="sectionOne">
    <lifecycle-callbacks>
      <lifecycle-callback type="prePersist" method="onPrePersist"/>
      <lifecycle-callback type="preUpdate" method="onPreUpdate"/>
    </lifecycle-callbacks>
    <id name="id" type="integer">
      <generator strategy="AUTO"/>
    </id>
    <one-to-one field="sectionTwo" target-entity="My\Namespace\Entity\SectionOne">
      <join-column name="sectionTwo_id" referenced-column-name="id"/>
    </one-to-one>
  </entity>
</doctrine-mapping>

EOT;
        //@codingStandardsIgnoreEnd

        $this->assertInstanceOf(Writable::class, $writable);
        $this->assertSame("My\\Namespace\\Resources\\config\\doctrine\\", $writable->getNamespace());
        $this->assertSame("SectionOne.orm.xml", $writable->getFilename());
        $this->assertSame($expected, $writable->getTemplate());
        $this->assertCount(0, $this->generator->getBuildMessages());
    }

    /**
     * @test
     * @covers ::generateBySection
     */
    public function it_should_fail_generating_with_a_nonexistent_class()
    {
        $sectionOne = $this->givenASectionWithName('One');

        $fieldtypeConfigDoctrine = FieldTypeGeneratorConfig::fromArray(
            [
                'doctrine' => [
                    'unknownKey' => "fakeClass"
                ]
            ]
        );

        $fieldType = Mockery::mock(FieldTypeInterface::class);
        $fieldType->shouldReceive('getFieldTypeGeneratorConfig')
            ->twice()
            ->andReturn($fieldtypeConfigDoctrine);
        $fieldType->shouldReceive('getFullyQualifiedClassName')
            ->andReturn(FullyQualifiedClassName::fromString('\My\Namespace\Field'));
        $fieldType->shouldReceive('getType')->andReturn(Type::fromString('EmailType'));
        $fieldType->shouldReceive('directory')->andReturn(__DIR__ . '/../../../../src/FieldType/Relationship');

        $fieldOne = $this->givenARelationshipFieldWithNameKindAndTo('One', 'one-to-one', 'Two');

        $this->fieldManager->shouldReceive('readByHandles')
            ->once()
            ->andReturn([$fieldOne]);

        $this->container->shouldReceive('get')
            ->twice()
            ->andReturn($fieldType);

        $writable = $this->generator->generateBySection($sectionOne);
        $this->assertInstanceOf(Writable::class, $writable);
        $this->assertSame("My\\Namespace\\Resources\\config\\doctrine\\", $writable->getNamespace());
        $this->assertSame("SectionOne.orm.xml", $writable->getFilename());
        $this->assertSame($this->givenXmlResult(), $writable->getTemplate());
        $this->assertCount(1, $this->generator->getBuildMessages());
    }

    /**
     * @test
     * @covers ::generateBySection
     */
    public function it_should_generate_section_and_skip_to_catch_block()
    {
        $sectionOne = $this->givenASectionWithName('One');

        $fieldtypeConfigDoctrine = FieldTypeGeneratorConfig::fromArray(
            [
                'doctrine' => [
                    'oneToOne' => DoctrineOneToOneGenerator::class
                ]
            ]
        );

        $fieldType = Mockery::mock(FieldTypeInterface::class);
        $fieldType->shouldReceive('getFieldTypeGeneratorConfig')
            ->twice()
            ->andReturn($fieldtypeConfigDoctrine);
        $fieldType->shouldReceive('getFullyQualifiedClassName')
            ->andReturn(FullyQualifiedClassName::fromString('\My\Namespace\Field'));
        $fieldType->shouldReceive('getType')->andReturn(Type::fromString('Email'));

        $fieldOne = $this->givenARelationshipFieldWithNameKindAndTo('One', 'one-to-one', 'Two');

        $this->fieldManager->shouldReceive('readByHandles')
            ->once()
            ->andReturn([$fieldOne]);

        $this->container->shouldReceive('get')
            ->times(3)
            ->andReturn($fieldType);

        $writable = $this->generator->generateBySection($sectionOne);
        $this->assertInstanceOf(Writable::class, $writable);
        $this->assertSame("My\\Namespace\\Resources\\config\\doctrine\\", $writable->getNamespace());
        $this->assertSame("SectionOne.orm.xml", $writable->getFilename());
        $this->assertSame($this->givenXmlResult(), $writable->getTemplate());
        $this->assertCount(1, $this->generator->getBuildMessages());
    }

    /**
     * @test
     * @covers ::generateBySection
     */
    public function it_should_throw_exception_when_section_is_ignored()
    {
        $this->expectException(IgnoredSectionException::class);
        $sectionOne = $this->givenAnIgnoredSection();
        $this->generator->generateBySection($sectionOne);
    }

    /**
     * @test
     * @covers ::generateBySection
     */
    public function it_should_add_unique_constraints_part_and_ignore_third_field()
    {
        $sectionOne = $this->givenASectionWithNameAndUniqueConstraint(
            'One',
            ['title', 'sectionTwo_id', 'nonExisting']
        );

        $this->sectionManager->shouldReceive('readByHandle')
            ->twice()
            ->andReturn($sectionOne);

        $fieldtypeConfigDoctrineOneToOne = FieldTypeGeneratorConfig::fromArray(
            [
                'doctrine' => [
                    'oneToOne' => DoctrineOneToOneGenerator::class
                ]
            ]
        );

        $fieldtypeConfigDoctrineTextInput = FieldTypeGeneratorConfig::fromArray(
            [
                'doctrine' => [
                    'fields' => DoctrineFieldGenerator::class
                ]
            ]
        );

        $fieldType = Mockery::mock(FieldTypeInterface::class);
        $fieldType->shouldReceive('getFieldTypeGeneratorConfig')
            ->times(2)
            ->andReturn($fieldtypeConfigDoctrineOneToOne);
        $fieldType->shouldReceive('getFieldTypeGeneratorConfig')
            ->times(3)
            ->andReturn($fieldtypeConfigDoctrineTextInput);
        $fieldType->shouldReceive('directory')
            ->once()
            ->andReturn(__DIR__ . '/../../../../src/FieldType/Relationship');

        $fieldType->shouldReceive('directory')
            ->once()
            ->andReturn(__DIR__ . '/../../../../src/FieldType/TextInput');

        $fieldOne = $this->givenARelationshipFieldWithNameKindAndTo('One', 'one-to-one', 'Two');
        $fieldTwo = $this->givenAFieldWithNameTypeAndIgnore('title', 'TextInput');
        $fieldThree = $this->givenAFieldWithNameTypeAndIgnore('ignored', 'TextInput', true);

        $this->fieldManager->shouldReceive('readByHandles')
            ->once()
            ->andReturn([$fieldOne, $fieldTwo, $fieldThree]);

        $this->container->shouldReceive('get')
            ->times(7)
            ->andReturn($fieldType);

        $writable = $this->generator->generateBySection($sectionOne);
        $this->assertInstanceOf(Writable::class, $writable);
        $this->assertSame("My\\Namespace\\Resources\\config\\doctrine\\", $writable->getNamespace());
        $this->assertSame("SectionOne.orm.xml", $writable->getFilename());
        $this->assertSame($this->givenXmlResultWithUniqueConstraint(), $writable->getTemplate());
        $this->assertCount(1, $this->generator->getBuildMessages());
        $this->assertSame(
            'Unique field nonExisting not found in fields of section',
            $this->generator->getBuildMessages()[0]
        );
    }

    private function givenASectionWithName($name)
    {
        $sectionName = 'Section ' . $name;
        $sectionHandle = 'section' . $name;

        $sectionConfig = SectionConfig::fromArray([
            'section' => [
                'name' => $sectionName,
                'handle' => $sectionHandle,
                'fields' => [
                    'title',
                    'body',
                    'created'
                ],
                'slug' => ['title'],
                'default' => 'title',
                'namespace' => 'My\\Namespace'
            ]
        ]);

        $section = new Section();

        $section->setName($sectionName);
        $section->setHandle($sectionHandle);
        $section->setConfig($sectionConfig->toArray());
        $section->setVersion(1);
        $section->setCreated(new \DateTime());
        $section->setUpdated(new \DateTime());

        return $section;
    }

    private function givenASectionWithNameAndUniqueConstraint(string $name, array $constraints)
    {
        $section = $this->givenASectionWithName($name);

        $sectionConfig = $section->getConfig() ->toArray();
        $sectionConfig['section']['generator'] = [
            'doctrine' => [
                'uniqueConstraint' => $constraints
            ]
        ];

        $section->setConfig($sectionConfig);

        return $section;
    }

    private function givenAnIgnoredSection()
    {
        $section = $this->givenASectionWithName('ignored');

        $sectionConfig = $section->getConfig() ->toArray();
        $sectionConfig['section']['generator'] = [
            'doctrine' => [
                'ignore' => true
            ]
        ];

        $section->setConfig($sectionConfig);

        return $section;
    }

    private function givenARelationshipFieldWithNameKindAndTo($name, $kind, $to)
    {
        $fieldName = 'Field ' . $name;
        $fieldHandle = 'field' . $name;
        $field = new Field();
        $field->setName($fieldName);
        $field->setHandle($fieldHandle);

        $fieldConfig = FieldConfig::fromArray([
            'field' => [
                'name' => $fieldName,
                'handle' => $fieldHandle,
                'kind' => $kind,
                'to' => 'section' . $to,
                'relationship-type' => 'unidirectional',
                'owner' => true
            ]
        ]);

        $field->setConfig($fieldConfig->toArray());

        $fieldType = new FieldType();
        $fieldType->setFullyQualifiedClassName('\\My\\Namespace\\FieldTypeClass' . $name);
        $fieldType->setType('Relationship');

        $field->setFieldType($fieldType);

        return $field;
    }

    private function givenAFieldWithNameTypeAndIgnore($name, $type, $ignore = false)
    {
        $field = new Field();
        $field->setName($name);
        $field->setHandle($name);

        $array = [
            'field' => [
                'name' => $name,
                'handle' => $name,
                'kind' => ''
            ]
        ];

        if ($ignore) {
            $array['field']['generator'] = [
                'doctrine' => [
                    'ignore' => true
                ]
            ];
        }

        $fieldConfig = FieldConfig::fromArray($array);

        $field->setConfig($fieldConfig->toArray());

        $fieldType = new FieldType();
        $fieldType->setFullyQualifiedClassName('\\My\\Namespace\\FieldTypeClass' . $name);
        $fieldType->setType($type);

        $field->setFieldType($fieldType);

        return $field;
    }

    private function givenXmlResult()
    {
        //@codingStandardsIgnoreStart
        $expected = <<<TXT
<?xml version="1.0"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping http://raw.github.com/doctrine/doctrine2/master/doctrine-mapping.xsd">
  <entity name="My\Namespace\Entity\SectionOne" table="sectionOne">
    <lifecycle-callbacks>
      <lifecycle-callback type="prePersist" method="onPrePersist"/>
      <lifecycle-callback type="preUpdate" method="onPreUpdate"/>
    </lifecycle-callbacks>
    <id name="id" type="integer">
      <generator strategy="AUTO"/>
    </id>
  </entity>
</doctrine-mapping>

TXT;
        //@codingStandardsIgnoreEnd
        return $expected;
    }

    private function givenXmlResultWithUniqueConstraint()
    {
        //@codingStandardsIgnoreStart
        $expected = <<<TXT
<?xml version="1.0"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping http://raw.github.com/doctrine/doctrine2/master/doctrine-mapping.xsd">
  <entity name="My\Namespace\Entity\SectionOne" table="sectionOne">
    <unique-constraints>
      <unique-constraint columns="title,sectionTwo_id,nonExisting" name="search_idx"/>
    </unique-constraints>
    <lifecycle-callbacks>
      <lifecycle-callback type="prePersist" method="onPrePersist"/>
      <lifecycle-callback type="preUpdate" method="onPreUpdate"/>
    </lifecycle-callbacks>
    <id name="id" type="integer">
      <generator strategy="AUTO"/>
    </id>
    <field name="title" nullable="true" type="string"/>
    <one-to-one field="sectionTwo" target-entity="My\Namespace\Entity\SectionOne">
      <join-column name="sectionTwo_id" referenced-column-name="id"/>
    </one-to-one>
  </entity>
</doctrine-mapping>

TXT;
        //@codingStandardsIgnoreEnd
        return $expected;
    }
}
