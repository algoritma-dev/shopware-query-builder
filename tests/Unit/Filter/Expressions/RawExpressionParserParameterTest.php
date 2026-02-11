<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Filter\Expressions;

use Algoritma\ShopwareQueryBuilder\Filter\Expressions\RawExpressionParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RawExpressionParser::class)]
class RawExpressionParserParameterTest extends TestCase
{
    private RawExpressionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RawExpressionParser();
    }

    public function testIsParameterReturnsTrueForValidParameter(): void
    {
        $this->assertTrue($this->parser->isParameter(':status'));
        $this->assertTrue($this->parser->isParameter(':minPrice'));
        $this->assertTrue($this->parser->isParameter(':_private'));
        $this->assertTrue($this->parser->isParameter(':param123'));
    }

    public function testIsParameterReturnsFalseForInvalidParameter(): void
    {
        $this->assertFalse($this->parser->isParameter('status'));
        $this->assertFalse($this->parser->isParameter('123'));
        $this->assertFalse($this->parser->isParameter(':123'));
        $this->assertFalse($this->parser->isParameter('::double'));
        $this->assertFalse($this->parser->isParameter(':invalid-name'));
    }

    public function testExtractParameterName(): void
    {
        $this->assertSame('status', $this->parser->extractParameterName(':status'));
        $this->assertSame('minPrice', $this->parser->extractParameterName(':minPrice'));
        $this->assertSame('_private', $this->parser->extractParameterName(':_private'));
    }

    public function testExtractParameterNameThrowsExceptionForInvalidParameter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'status' is not a valid parameter placeholder");

        $this->parser->extractParameterName('status');
    }

    public function testParseExpressionWithParameter(): void
    {
        $result = $this->parser->parse('status = :status');

        $this->assertFalse($result['isCompound']);
        $this->assertCount(1, $result['conditions']);
        $this->assertSame('status', $result['conditions'][0]['field']);
        $this->assertSame('=', $result['conditions'][0]['operator']);
        $this->assertSame(':status', $result['conditions'][0]['value']);
    }

    public function testParseExpressionWithMultipleParameters(): void
    {
        $result = $this->parser->parse('price >= :minPrice AND price <= :maxPrice');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('AND', $result['operator']);
        $this->assertCount(2, $result['conditions']);

        $this->assertSame('price', $result['conditions'][0]['field']);
        $this->assertSame('>=', $result['conditions'][0]['operator']);
        $this->assertSame(':minPrice', $result['conditions'][0]['value']);

        $this->assertSame('price', $result['conditions'][1]['field']);
        $this->assertSame('<=', $result['conditions'][1]['operator']);
        $this->assertSame(':maxPrice', $result['conditions'][1]['value']);
    }

    public function testParseExpressionWithParameterInArray(): void
    {
        $result = $this->parser->parse('status IN (:statuses)');

        $this->assertFalse($result['isCompound']);
        $this->assertSame('status', $result['conditions'][0]['field']);
        $this->assertSame('in', $result['conditions'][0]['operator']);
        $this->assertSame(':statuses', $result['conditions'][0]['value']);
    }

    public function testParseExpressionWithLikeParameter(): void
    {
        $result = $this->parser->parse('name LIKE :searchTerm');

        $this->assertFalse($result['isCompound']);
        $this->assertSame('name', $result['conditions'][0]['field']);
        $this->assertSame('like', $result['conditions'][0]['operator']);
        $this->assertSame(':searchTerm', $result['conditions'][0]['value']);
    }

    #[DataProvider('comparisonOperatorProvider')]
    public function testParseExpressionWithVariousOperators(string $operator): void
    {
        $result = $this->parser->parse("field {$operator} :param");

        $this->assertFalse($result['isCompound']);
        $this->assertSame('field', $result['conditions'][0]['field']);
        $this->assertSame(':param', $result['conditions'][0]['value']);
    }

    /**
     * @return array<string, array<string>>
     */
    public static function comparisonOperatorProvider(): array
    {
        return [
            'equals' => ['='],
            'not equals' => ['!='],
            'not equals alt' => ['<>'],
            'greater than' => ['>'],
            'greater than or equal' => ['>='],
            'less than' => ['<'],
            'less than or equal' => ['<='],
        ];
    }

    public function testParseCompoundExpressionWithParameters(): void
    {
        $result = $this->parser->parse('status = :status OR featured = :featured');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('OR', $result['operator']);
        $this->assertCount(2, $result['conditions']);

        $this->assertSame(':status', $result['conditions'][0]['value']);
        $this->assertSame(':featured', $result['conditions'][1]['value']);
    }

    public function testParseExpressionMixingParametersAndLiterals(): void
    {
        $result = $this->parser->parse('status = :status AND active = true');

        $this->assertTrue($result['isCompound']);
        $this->assertSame('AND', $result['operator']);
        $this->assertSame(':status', $result['conditions'][0]['value']);
        $this->assertTrue($result['conditions'][1]['value']);
    }
}
