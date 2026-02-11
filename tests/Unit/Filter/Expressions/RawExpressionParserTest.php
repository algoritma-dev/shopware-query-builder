<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Filter\Expressions;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidOperatorException;
use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RawExpressionParser::class)]
class RawExpressionParserTest extends TestCase
{
    private RawExpressionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RawExpressionParser();
    }

    // Simple Expression Tests

    public function testParseSimpleEqualityWithBoolean(): void
    {
        $result = $this->parser->parse('active = true');

        $this->assertFalse($result['isCompound']);
        $this->assertNull($result['operator']);
        $this->assertCount(1, $result['conditions']);

        $condition = $result['conditions'][0];
        $this->assertSame('active', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertTrue($condition['value']);
    }

    public function testParseSimpleEqualityWithInteger(): void
    {
        $result = $this->parser->parse('stock = 10');

        $condition = $result['conditions'][0];
        $this->assertSame('stock', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertSame(10, $condition['value']);
    }

    public function testParseSimpleEqualityWithString(): void
    {
        $result = $this->parser->parse('status = "active"');

        $condition = $result['conditions'][0];
        $this->assertSame('status', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertSame('active', $condition['value']);
    }

    public function testParseSimpleEqualityWithSingleQuotedString(): void
    {
        $result = $this->parser->parse("name = 'John'");

        $condition = $result['conditions'][0];
        $this->assertSame('name', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertSame('John', $condition['value']);
    }

    public function testParseSimpleEqualityWithUnquotedString(): void
    {
        $result = $this->parser->parse('status = active');

        $condition = $result['conditions'][0];
        $this->assertSame('status', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertSame('active', $condition['value']);
    }

    public function testParseSimpleEqualityWithFloat(): void
    {
        $result = $this->parser->parse('price = 19.99');

        $condition = $result['conditions'][0];
        $this->assertSame('price', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertSame(19.99, $condition['value']);
    }

    public function testParseSimpleEqualityWithNull(): void
    {
        $result = $this->parser->parse('deletedAt = NULL');

        $condition = $result['conditions'][0];
        $this->assertSame('deletedAt', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertNull($condition['value']);
    }

    public function testParseSimpleEqualityWithFalse(): void
    {
        $result = $this->parser->parse('active = false');

        $condition = $result['conditions'][0];
        $this->assertSame('active', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertFalse($condition['value']);
    }

    // Comparison Operators

    public function testParseGreaterThan(): void
    {
        $result = $this->parser->parse('stock > 10');

        $condition = $result['conditions'][0];
        $this->assertSame('stock', $condition['field']);
        $this->assertSame('>', $condition['operator']);
        $this->assertSame(10, $condition['value']);
    }

    public function testParseGreaterThanOrEqual(): void
    {
        $result = $this->parser->parse('stock >= 10');

        $condition = $result['conditions'][0];
        $this->assertSame('stock', $condition['field']);
        $this->assertSame('>=', $condition['operator']);
        $this->assertSame(10, $condition['value']);
    }

    public function testParseLessThan(): void
    {
        $result = $this->parser->parse('stock < 100');

        $condition = $result['conditions'][0];
        $this->assertSame('stock', $condition['field']);
        $this->assertSame('<', $condition['operator']);
        $this->assertSame(100, $condition['value']);
    }

    public function testParseLessThanOrEqual(): void
    {
        $result = $this->parser->parse('price <= 50');

        $condition = $result['conditions'][0];
        $this->assertSame('price', $condition['field']);
        $this->assertSame('<=', $condition['operator']);
        $this->assertSame(50, $condition['value']);
    }

    public function testParseNotEquals(): void
    {
        $result = $this->parser->parse('status != deleted');

        $condition = $result['conditions'][0];
        $this->assertSame('status', $condition['field']);
        $this->assertSame('!=', $condition['operator']);
        $this->assertSame('deleted', $condition['value']);
    }

    public function testParseNotEqualsAlternativeSyntax(): void
    {
        $result = $this->parser->parse('status <> deleted');

        $condition = $result['conditions'][0];
        $this->assertSame('status', $condition['field']);
        $this->assertSame('<>', $condition['operator']);
        $this->assertSame('deleted', $condition['value']);
    }

    // Multi-word Operators

    public function testParseLikeOperator(): void
    {
        $result = $this->parser->parse('name LIKE "%test%"');

        $condition = $result['conditions'][0];
        $this->assertSame('name', $condition['field']);
        $this->assertSame('like', $condition['operator']);
        $this->assertSame('%test%', $condition['value']);
    }

    public function testParseLikeOperatorCaseInsensitive(): void
    {
        $result = $this->parser->parse('name like "test"');

        $condition = $result['conditions'][0];
        $this->assertSame('name', $condition['field']);
        $this->assertSame('like', $condition['operator']);
        $this->assertSame('test', $condition['value']);
    }

    public function testParseInOperator(): void
    {
        $result = $this->parser->parse('id IN (1,2,3)');

        $condition = $result['conditions'][0];
        $this->assertSame('id', $condition['field']);
        $this->assertSame('in', $condition['operator']);
        $this->assertIsArray($condition['value']);
        $this->assertSame([1, 2, 3], $condition['value']);
    }

    public function testParseInOperatorWithStrings(): void
    {
        $result = $this->parser->parse('status IN ("active","pending","draft")');

        $condition = $result['conditions'][0];
        $this->assertSame('status', $condition['field']);
        $this->assertSame('in', $condition['operator']);
        $this->assertSame(['active', 'pending', 'draft'], $condition['value']);
    }

    public function testParseNotInOperator(): void
    {
        $result = $this->parser->parse('id NOT IN (1,2,3)');

        $condition = $result['conditions'][0];
        $this->assertSame('id', $condition['field']);
        $this->assertSame('not in', $condition['operator']);
        $this->assertSame([1, 2, 3], $condition['value']);
    }

    public function testParseIsNull(): void
    {
        $result = $this->parser->parse('deletedAt IS NULL');

        $condition = $result['conditions'][0];
        $this->assertSame('deletedAt', $condition['field']);
        $this->assertSame('is null', $condition['operator']);
        $this->assertNull($condition['value']);
    }

    public function testParseIsNotNull(): void
    {
        $result = $this->parser->parse('deletedAt IS NOT NULL');

        $condition = $result['conditions'][0];
        $this->assertSame('deletedAt', $condition['field']);
        $this->assertSame('is not null', $condition['operator']);
        $this->assertNull($condition['value']);
    }

    // Compound Expressions with AND

    public function testParseCompoundExpressionWithAND(): void
    {
        $result = $this->parser->parse('stock > 10 AND active = true');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('AND', $result['operator']);
        $this->assertCount(2, $result['conditions']);

        $this->assertSame('stock', $result['conditions'][0]['field']);
        $this->assertSame('>', $result['conditions'][0]['operator']);
        $this->assertSame(10, $result['conditions'][0]['value']);

        $this->assertSame('active', $result['conditions'][1]['field']);
        $this->assertSame('=', $result['conditions'][1]['operator']);
        $this->assertTrue($result['conditions'][1]['value']);
    }

    public function testParseCompoundExpressionWithMultipleAND(): void
    {
        $result = $this->parser->parse('a = 1 AND b = 2 AND c = 3');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('AND', $result['operator']);
        $this->assertCount(3, $result['conditions']);

        $this->assertSame('a', $result['conditions'][0]['field']);
        $this->assertSame('b', $result['conditions'][1]['field']);
        $this->assertSame('c', $result['conditions'][2]['field']);
    }

    public function testParseCompoundExpressionWithANDCaseInsensitive(): void
    {
        $result = $this->parser->parse('stock > 10 and active = true');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('AND', $result['operator']);
        $this->assertCount(2, $result['conditions']);
    }

    // Compound Expressions with OR

    public function testParseCompoundExpressionWithOR(): void
    {
        $result = $this->parser->parse('featured = true OR promoted = true');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('OR', $result['operator']);
        $this->assertCount(2, $result['conditions']);

        $this->assertSame('featured', $result['conditions'][0]['field']);
        $this->assertTrue($result['conditions'][0]['value']);

        $this->assertSame('promoted', $result['conditions'][1]['field']);
        $this->assertTrue($result['conditions'][1]['value']);
    }

    public function testParseCompoundExpressionWithORCaseInsensitive(): void
    {
        $result = $this->parser->parse('featured = true or promoted = true');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('OR', $result['operator']);
    }

    public function testParseCompoundExpressionWithMultipleOR(): void
    {
        $result = $this->parser->parse('status = "draft" OR status = "pending" OR status = "review"');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('OR', $result['operator']);
        $this->assertCount(3, $result['conditions']);
    }

    // isCompoundExpression method tests

    public function testIsCompoundExpressionReturnsTrueForAND(): void
    {
        $this->assertTrue($this->parser->isCompoundExpression('a = 1 AND b = 2'));
    }

    public function testIsCompoundExpressionReturnsTrueForOR(): void
    {
        $this->assertTrue($this->parser->isCompoundExpression('a = 1 OR b = 2'));
    }

    public function testIsCompoundExpressionReturnsFalseForSimple(): void
    {
        $this->assertFalse($this->parser->isCompoundExpression('active = true'));
    }

    // Edge Cases

    public function testParseExpressionWithExtraWhitespace(): void
    {
        $result = $this->parser->parse('  stock   >   10  ');

        $condition = $result['conditions'][0];
        $this->assertSame('stock', $condition['field']);
        $this->assertSame('>', $condition['operator']);
        $this->assertSame(10, $condition['value']);
    }

    public function testParseExpressionWithFieldAlias(): void
    {
        $result = $this->parser->parse('p.active = true');

        $condition = $result['conditions'][0];
        $this->assertSame('p.active', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertTrue($condition['value']);
    }

    public function testParseExpressionWithNestedField(): void
    {
        $result = $this->parser->parse('manufacturer.name = "test"');

        $condition = $result['conditions'][0];
        $this->assertSame('manufacturer.name', $condition['field']);
        $this->assertSame('=', $condition['operator']);
        $this->assertSame('test', $condition['value']);
    }

    public function testParseCompoundExpressionWithDifferentOperators(): void
    {
        $result = $this->parser->parse('stock > 0 AND price <= 100');

        $this->assertTrue($result['isCompound']);
        $this->assertCount(2, $result['conditions']);

        $this->assertSame('stock', $result['conditions'][0]['field']);
        $this->assertSame('>', $result['conditions'][0]['operator']);

        $this->assertSame('price', $result['conditions'][1]['field']);
        $this->assertSame('<=', $result['conditions'][1]['operator']);
    }

    public function testParseRawExpressionIsStoredInCondition(): void
    {
        $result = $this->parser->parse('stock > 10');

        $condition = $result['conditions'][0];
        $this->assertArrayHasKey('raw', $condition);
        $this->assertSame('stock > 10', $condition['raw']);
    }

    // Error Cases

    public function testParseThrowsExceptionForInvalidExpression(): void
    {
        $this->expectException(InvalidOperatorException::class);
        $this->expectExceptionMessage("Could not parse expression: 'invalidexpression'");

        $this->parser->parse('invalidexpression');
    }

    public function testParseThrowsExceptionForMissingOperator(): void
    {
        $this->expectException(InvalidOperatorException::class);

        $this->parser->parse('field value');
    }

    // Zero value tests

    public function testParseZeroValue(): void
    {
        $result = $this->parser->parse('stock = 0');

        $condition = $result['conditions'][0];
        $this->assertSame('stock', $condition['field']);
        $this->assertSame(0, $condition['value']);
    }

    public function testParseNegativeValue(): void
    {
        $result = $this->parser->parse('temperature < -10');

        $condition = $result['conditions'][0];
        $this->assertSame('temperature', $condition['field']);
        $this->assertSame(-10, $condition['value']);
    }
}
