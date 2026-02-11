<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\Filter;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidOperatorException;
use Algoritma\ShopwareQueryBuilder\Filter\FilterFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;

#[CoversClass(FilterFactory::class)]
class FilterFactoryTest extends TestCase
{
    private FilterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FilterFactory();
    }

    public function testCreateEqualsFilter(): void
    {
        $filter = $this->factory->create('active', '=', true);

        $this->assertInstanceOf(EqualsFilter::class, $filter);
    }

    public function testCreateNotEqualsFilter(): void
    {
        $filter = $this->factory->create('active', '!=', false);

        $this->assertInstanceOf(NotFilter::class, $filter);
    }

    public function testCreateGreaterThanFilter(): void
    {
        $filter = $this->factory->create('stock', '>', 10);

        $this->assertInstanceOf(RangeFilter::class, $filter);
    }

    public function testCreateGreaterThanOrEqualFilter(): void
    {
        $filter = $this->factory->create('stock', '>=', 10);

        $this->assertInstanceOf(RangeFilter::class, $filter);
    }

    public function testCreateLessThanFilter(): void
    {
        $filter = $this->factory->create('price', '<', 100);

        $this->assertInstanceOf(RangeFilter::class, $filter);
    }

    public function testCreateLessThanOrEqualFilter(): void
    {
        $filter = $this->factory->create('price', '<=', 100);

        $this->assertInstanceOf(RangeFilter::class, $filter);
    }

    public function testCreateContainsFilter(): void
    {
        $filter = $this->factory->create('name', 'like', 'test');

        $this->assertInstanceOf(ContainsFilter::class, $filter);
    }

    public function testCreateInFilter(): void
    {
        $filter = $this->factory->create('id', 'in', ['id1', 'id2']);

        $this->assertInstanceOf(EqualsAnyFilter::class, $filter);
    }

    public function testCreateNotInFilter(): void
    {
        $filter = $this->factory->create('id', 'not in', ['id1', 'id2']);

        $this->assertInstanceOf(NotFilter::class, $filter);
    }

    public function testCreateNullFilter(): void
    {
        $filter = $this->factory->create('deletedAt', 'is null', null);

        $this->assertInstanceOf(EqualsFilter::class, $filter);
    }

    public function testCreateNotNullFilter(): void
    {
        $filter = $this->factory->create('parentId', 'is not null', null);

        $this->assertInstanceOf(NotFilter::class, $filter);
    }

    public function testCreatePrefixFilter(): void
    {
        $filter = $this->factory->create('productNumber', 'starts with', 'SW-');

        $this->assertInstanceOf(PrefixFilter::class, $filter);
    }

    public function testCreateSuffixFilter(): void
    {
        $filter = $this->factory->create('productNumber', 'ends with', '-001');

        $this->assertInstanceOf(SuffixFilter::class, $filter);
    }

    public function testCreateThrowsExceptionForInvalidOperator(): void
    {
        $this->expectException(InvalidOperatorException::class);

        $this->factory->create('field', 'invalid', 'value');
    }
}
