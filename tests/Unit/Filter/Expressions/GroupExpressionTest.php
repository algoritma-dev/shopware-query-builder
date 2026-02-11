<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Filter\Expressions;

use Algoritma\ShopwareQueryBuilder\Filter\Expressions\GroupExpression;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\WhereExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupExpression::class)]
#[UsesClass(WhereExpression::class)]
class GroupExpressionTest extends TestCase
{
    public function testGetExpressions(): void
    {
        $expressions = [
            new WhereExpression('active', '=', true),
            new WhereExpression('stock', '>', 0),
        ];

        $group = new GroupExpression($expressions, 'AND');

        $this->assertSame($expressions, $group->getExpressions());
    }

    public function testGetOperator(): void
    {
        $expressions = [
            new WhereExpression('active', '=', true),
        ];

        $group = new GroupExpression($expressions, 'OR');

        $this->assertSame('OR', $group->getOperator());
    }

    public function testDefaultOperatorIsAnd(): void
    {
        $expressions = [
            new WhereExpression('active', '=', true),
        ];

        $group = new GroupExpression($expressions);

        $this->assertSame('AND', $group->getOperator());
    }
}
