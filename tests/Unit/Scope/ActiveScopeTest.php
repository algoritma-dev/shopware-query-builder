<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Scope;

use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\WhereExpression;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use Algoritma\ShopwareQueryBuilder\Mapping\AssociationResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\EntityDefinitionResolver;
use Algoritma\ShopwareQueryBuilder\Mapping\PropertyResolver;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\QueryBuilder;
use Algoritma\ShopwareQueryBuilder\Scope\ActiveScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;

#[CoversClass(ActiveScope::class)]
#[UsesClass(FilterFactory::class)]
#[UsesClass(RawExpressionParser::class)]
#[UsesClass(QueryBuilder::class)]
#[UsesClass(WhereExpression::class)]
class ActiveScopeTest extends TestCase
{
    public function testApplyAddsActiveCondition(): void
    {
        $definitionResolver = $this->createMock(EntityDefinitionResolver::class);
        $propertyResolver = $this->createMock(PropertyResolver::class);
        $associationResolver = $this->createMock(AssociationResolver::class);
        $filterFactory = new FilterFactory();

        $definitionResolver
            ->method('getDefinition')
            ->willReturn($this->createMock(ProductDefinition::class));

        $propertyResolver
            ->method('resolve')
            ->willReturn('active');

        $queryBuilder = new QueryBuilder(
            ProductEntity::class,
            $definitionResolver,
            $propertyResolver,
            $associationResolver,
            $filterFactory,
            new RawExpressionParser()
        );

        $scope = new ActiveScope();
        $scope->apply($queryBuilder);

        $expressions = $queryBuilder->getWhereExpressions();

        $this->assertCount(1, $expressions);
        $this->assertSame('active', $expressions[0]->getField());
        $this->assertSame('=', $expressions[0]->getOperator());
        $this->assertTrue($expressions[0]->getValue());
    }
}
