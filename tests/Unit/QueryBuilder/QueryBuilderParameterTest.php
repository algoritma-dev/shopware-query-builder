<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidParameterException;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\ParameterBag;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;

#[CoversClass(QueryBuilder::class)]
#[CoversClass(ParameterBag::class)]
class QueryBuilderParameterTest extends TestCase
{
    private QueryBuilder $queryBuilder;

    /**
     * @var MockObject&EntityDefinitionResolver
     */
    private MockObject $definitionResolver;

    /**
     * @var MockObject&PropertyResolver
     */
    private MockObject $propertyResolver;

    /**
     * @var MockObject&AssociationResolver
     */
    private MockObject $associationResolver;

    protected function setUp(): void
    {
        $this->definitionResolver = $this->createMock(EntityDefinitionResolver::class);
        $this->propertyResolver = $this->createMock(PropertyResolver::class);
        $this->associationResolver = $this->createMock(AssociationResolver::class);

        $this->definitionResolver
            ->method('getDefinition')
            ->willReturn($this->createMock(ProductDefinition::class));

        $this->queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $this->definitionResolver,
            $this->propertyResolver,
            $this->associationResolver,
            new FilterFactory(),
            new RawExpressionParser()
        );
    }

    public function testSetParameter(): void
    {
        $result = $this->queryBuilder->setParameter('status', 'active');

        $this->assertSame($this->queryBuilder, $result);
        $this->assertTrue($this->queryBuilder->getParameters()->has('status'));
        $this->assertSame('active', $this->queryBuilder->getParameters()->get('status'));
    }

    public function testSetParameterWithColonPrefix(): void
    {
        $this->queryBuilder->setParameter(':status', 'active');

        $this->assertTrue($this->queryBuilder->getParameters()->has('status'));
        $this->assertSame('active', $this->queryBuilder->getParameters()->get('status'));
    }

    public function testSetParameters(): void
    {
        $parameters = [
            'status' => 'active',
            'minPrice' => 100,
            'maxPrice' => 500,
        ];

        $result = $this->queryBuilder->setParameters($parameters);

        $this->assertSame($this->queryBuilder, $result);
        $this->assertSame('active', $this->queryBuilder->getParameters()->get('status'));
        $this->assertSame(100, $this->queryBuilder->getParameters()->get('minPrice'));
        $this->assertSame(500, $this->queryBuilder->getParameters()->get('maxPrice'));
    }

    public function testSetParameterWithInvalidNameThrowsException(): void
    {
        $this->expectException(InvalidParameterException::class);

        $this->queryBuilder->setParameter('invalid-name', 'value');
    }

    public function testFluentChaining(): void
    {
        $result = $this->queryBuilder
            ->setParameter('status', 'active')
            ->setParameter('minPrice', 100)
            ->setParameter('maxPrice', 500);

        $this->assertSame($this->queryBuilder, $result);
        $this->assertSame(3, $this->queryBuilder->getParameters()->count());
    }

    public function testGetParameters(): void
    {
        $this->queryBuilder->setParameters([
            'status' => 'active',
            'price' => 100,
        ]);

        $parameterBag = $this->queryBuilder->getParameters();

        $this->assertInstanceOf(ParameterBag::class, $parameterBag);
        $this->assertSame(2, $parameterBag->count());
    }

    public function testSetParameterWithDifferentTypes(): void
    {
        $this->queryBuilder->setParameter('string', 'value');
        $this->queryBuilder->setParameter('int', 123);
        $this->queryBuilder->setParameter('float', 123.45);
        $this->queryBuilder->setParameter('bool', true);
        $this->queryBuilder->setParameter('nullValue', null);
        $this->queryBuilder->setParameter('array', [1, 2, 3]);

        $this->assertSame('value', $this->queryBuilder->getParameters()->get('string'));
        $this->assertSame(123, $this->queryBuilder->getParameters()->get('int'));
        $this->assertSame(123.45, $this->queryBuilder->getParameters()->get('float'));
        $this->assertTrue($this->queryBuilder->getParameters()->get('bool'));
        $this->assertNull($this->queryBuilder->getParameters()->get('nullValue'));
        $this->assertSame([1, 2, 3], $this->queryBuilder->getParameters()->get('array'));
    }
}
