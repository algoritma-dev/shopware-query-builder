<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Mapping;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidEntityException;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class EntityDefinitionResolverTest extends TestCase
{
    private EntityDefinitionResolver $resolver;

    /**
     * @var MockObject&DefinitionInstanceRegistry
     */
    private MockObject $registry;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->resolver = new EntityDefinitionResolver($this->registry);
    }

    public function testGetDefinitionReturnsCorrectDefinition(): void
    {
        $definition = $this->createMockDefinition();

        $this->registry
            ->expects($this->once())
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($definition);

        $result = $this->resolver->getDefinition(ProductEntity::class);

        $this->assertSame($definition, $result);
    }

    public function testGetDefinitionCachesResult(): void
    {
        $definition = $this->createMockDefinition();

        $this->registry
            ->expects($this->once()) // Should only be called once due to caching
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($definition);

        $this->resolver->getDefinition(ProductEntity::class);
        $result = $this->resolver->getDefinition(ProductEntity::class);

        $this->assertSame($definition, $result);
    }

    public function testGetDefinitionThrowsExceptionForInvalidClass(): void
    {
        $this->expectException(InvalidEntityException::class);
        $this->expectExceptionMessageMatches('/Definition class .* not found/');

        $this->resolver->getDefinition('InvalidEntity');
    }

    public function testGetEntityName(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->getEntityName(ProductEntity::class);

        $this->assertSame('product', $result);
    }

    public function testHasFieldReturnsTrueForExistingField(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->hasField(ProductEntity::class, 'active');

        $this->assertTrue($result);
    }

    public function testHasFieldReturnsFalseForNonExistingField(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->hasField(ProductEntity::class, 'nonExistingField123');

        $this->assertFalse($result);
    }

    public function testGetField(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->getField(ProductEntity::class, 'active');

        $this->assertInstanceOf(Field::class, $result);
        $this->assertSame('active', $result->getPropertyName());
    }

    public function testGetFieldThrowsExceptionForNonExistingField(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $this->expectException(InvalidEntityException::class);
        $this->expectExceptionMessageMatches('/Field .* does not exist/');

        $this->resolver->getField(ProductEntity::class, 'nonExistingField123');
    }

    public function testIsAssociationReturnsTrueForAssociation(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->isAssociation(ProductEntity::class, 'manufacturer');

        $this->assertTrue($result);
    }

    public function testIsAssociationReturnsFalseForNonAssociation(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->isAssociation(ProductEntity::class, 'active');

        $this->assertFalse($result);
    }

    public function testGetAvailableFields(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->getAvailableFields(ProductEntity::class);

        $this->assertContains('id', $result);
        $this->assertContains('active', $result);
        $this->assertContains('name', $result);
        $this->assertContains('stock', $result);
    }

    public function testGetAvailableAssociations(): void
    {
        $testDefinition = $this->createTestDefinition();

        $this->registry
            ->method('getByEntityName')
            ->with('product')
            ->willReturn($testDefinition);

        $result = $this->resolver->getAvailableAssociations(ProductEntity::class);

        $this->assertContains('manufacturer', $result);
    }

    private function createMockDefinition(): EntityDefinition
    {
        return $this->createMock(ProductDefinition::class);
    }

    private function createTestDefinition(): TestEntityDefinition
    {
        return new TestEntityDefinition();
    }
}

/**
 * Test stub for EntityDefinition with overridable getFields() behavior.
 */
class TestEntityDefinition extends EntityDefinition
{
    /**
     * @var string
     */
    public const ENTITY_NAME = 'product';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function setTestFields(CompiledFieldCollection $fields): void {}

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('id', 'id'),
            new StringField('name', 'name'),
            new BoolField('active', 'active'),
            new IntField('stock', 'stock'),
            new ManyToOneAssociationField('manufacturer', 'manufacturer_id', ProductDefinition::class, 'id'),
        ]);
    }
}
