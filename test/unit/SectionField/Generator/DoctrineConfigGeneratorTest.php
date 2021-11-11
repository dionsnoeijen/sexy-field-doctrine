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
 * @coversDefaultClass Tardigrades\SectionField\Generator\DoctrineConfigGenerator
 * @covers ::<private>
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

    public function setUp(): void
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
     * @covers ::generateElements
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

        $fieldOne = $this->givenAFieldWithNameKindAndTo('One', 'one-to-one', 'Two');

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
      <join-column name="sectionTwo_id" referenced-column-name="id" nullable="false" unique="false"/>
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
     * @covers ::generateElements
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

        $fieldOne = $this->givenAFieldWithNameKindAndTo('One', 'one-to-one', 'Two');

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
     * @covers ::generateElements
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

        $fieldOne = $this->givenAFieldWithNameKindAndTo('One', 'one-to-one', 'Two');

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

    private function givenAFieldWithNameKindAndTo($name, $kind, $to)
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
                'owner' => true,
                'nullable' => false
            ]
        ]);

        $field->setConfig($fieldConfig->toArray());

        $fieldType = new FieldType();
        $fieldType->setFullyQualifiedClassName('\\My\\Namespace\\FieldTypeClass' . $name);
        $fieldType->setType('Email');

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
}
