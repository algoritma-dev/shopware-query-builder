<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Filter;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidOperatorException;
use Algoritma\ShopwareQueryBuilder\Filter\OperatorMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OperatorMapperTest extends TestCase
{
    private OperatorMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OperatorMapper();
    }

    #[DataProvider('validOperatorProvider')]
    public function testGetFilterType(string $operator, string $expectedType): void
    {
        $this->assertSame($expectedType, $this->mapper->getFilterType($operator));
    }

    public function testGetFilterTypeThrowsExceptionForInvalidOperator(): void
    {
        $this->expectException(InvalidOperatorException::class);
        $this->expectExceptionMessage("Unknown operator: 'invalid'");

        $this->mapper->getFilterType('invalid');
    }

    public function testIsValidOperator(): void
    {
        $this->assertTrue($this->mapper->isValidOperator('='));
        $this->assertTrue($this->mapper->isValidOperator('like'));
        $this->assertTrue($this->mapper->isValidOperator('in'));
        $this->assertFalse($this->mapper->isValidOperator('invalid'));
    }

    public function testGetSupportedOperators(): void
    {
        $operators = $this->mapper->getSupportedOperators();

        $this->assertContains('=', $operators);
        $this->assertContains('>', $operators);
        $this->assertContains('like', $operators);
        $this->assertContains('in', $operators);
        $this->assertGreaterThan(10, count($operators));
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function validOperatorProvider(): array
    {
        return [
            ['=', 'equals'],
            ['==', 'equals'],
            ['!=', 'not_equals'],
            ['<>', 'not_equals'],
            ['>', 'range'],
            ['gt', 'range'],
            ['>=', 'range'],
            ['gte', 'range'],
            ['<', 'range'],
            ['lt', 'range'],
            ['<=', 'range'],
            ['lte', 'range'],
            ['LIKE', 'contains'],
            ['like', 'contains'],
            ['IN', 'in'],
            ['in', 'in'],
            ['NOT IN', 'not_in'],
            ['not in', 'not_in'],
            ['IS NULL', 'null'],
            ['is null', 'null'],
            ['IS NOT NULL', 'not_null'],
            ['is not null', 'not_null'],
            ['STARTS WITH', 'prefix'],
            ['starts with', 'prefix'],
            ['ENDS WITH', 'suffix'],
            ['ends with', 'suffix'],
        ];
    }
}
