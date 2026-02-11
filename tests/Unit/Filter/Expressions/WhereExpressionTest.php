<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Filter\Expressions;

use Algoritma\ShopwareQueryBuilder\Filter\Expressions\WhereExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhereExpression::class)]
class WhereExpressionTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $expression = new WhereExpression('active', '=', true);

        $this->assertSame('active', $expression->getField());
        $this->assertSame('=', $expression->getOperator());
        $this->assertTrue($expression->getValue());
    }

    public function testWithDifferentTypes(): void
    {
        $expression = new WhereExpression('stock', '>', 10);

        $this->assertSame('stock', $expression->getField());
        $this->assertSame('>', $expression->getOperator());
        $this->assertSame(10, $expression->getValue());
    }

    public function testWithNestedProperty(): void
    {
        $expression = new WhereExpression('manufacturer.name', '=', 'Test');

        $this->assertSame('manufacturer.name', $expression->getField());
        $this->assertSame('=', $expression->getOperator());
        $this->assertSame('Test', $expression->getValue());
    }

    public function testWithNullValue(): void
    {
        $expression = new WhereExpression('deletedAt', 'is null', null);

        $this->assertSame('deletedAt', $expression->getField());
        $this->assertSame('is null', $expression->getOperator());
        $this->assertNull($expression->getValue());
    }

    public function testWithArrayValue(): void
    {
        $ids = ['id1', 'id2', 'id3'];
        $expression = new WhereExpression('id', 'in', $ids);

        $this->assertSame('id', $expression->getField());
        $this->assertSame('in', $expression->getOperator());
        $this->assertSame($ids, $expression->getValue());
    }
}
