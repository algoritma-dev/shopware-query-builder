<?php

declare(strict_types=1);

namespace Algoritma\ShopwareQueryBuilder\Tests\Unit\QueryBuilder;

use Algoritma\ShopwareQueryBuilder\Exception\InvalidParameterException;
use Algoritma\ShopwareQueryBuilder\QueryBuilder\ParameterBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParameterBag::class)]
class ParameterBagTest extends TestCase
{
    private ParameterBag $parameterBag;

    protected function setUp(): void
    {
        $this->parameterBag = new ParameterBag();
    }

    public function testSetAndGetParameter(): void
    {
        $this->parameterBag->set('status', 'active');

        $this->assertSame('active', $this->parameterBag->get('status'));
    }

    public function testSetParameterWithColonPrefix(): void
    {
        $this->parameterBag->set(':status', 'active');

        $this->assertSame('active', $this->parameterBag->get('status'));
        $this->assertSame('active', $this->parameterBag->get(':status'));
    }

    public function testSetParameterReturnsFluentInterface(): void
    {
        $result = $this->parameterBag->set('status', 'active');

        $this->assertSame($this->parameterBag, $result);
    }

    public function testSetAllParameters(): void
    {
        $parameters = [
            'status' => 'active',
            'minPrice' => 100,
            'maxPrice' => 500,
        ];

        $this->parameterBag->setAll($parameters);

        $this->assertSame('active', $this->parameterBag->get('status'));
        $this->assertSame(100, $this->parameterBag->get('minPrice'));
        $this->assertSame(500, $this->parameterBag->get('maxPrice'));
    }

    public function testSetAllReturnsFluentInterface(): void
    {
        $result = $this->parameterBag->setAll(['status' => 'active']);

        $this->assertSame($this->parameterBag, $result);
    }

    public function testHasParameter(): void
    {
        $this->parameterBag->set('status', 'active');

        $this->assertTrue($this->parameterBag->has('status'));
        $this->assertTrue($this->parameterBag->has(':status'));
        $this->assertFalse($this->parameterBag->has('price'));
    }

    public function testGetNonExistentParameterThrowsException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage("Parameter ':nonexistent' is not set");

        $this->parameterBag->get('nonexistent');
    }

    public function testRemoveParameter(): void
    {
        $this->parameterBag->set('status', 'active');
        $this->assertTrue($this->parameterBag->has('status'));

        $this->parameterBag->remove('status');

        $this->assertFalse($this->parameterBag->has('status'));
    }

    public function testRemoveReturnsFluentInterface(): void
    {
        $this->parameterBag->set('status', 'active');
        $result = $this->parameterBag->remove('status');

        $this->assertSame($this->parameterBag, $result);
    }

    public function testGetAllParameters(): void
    {
        $parameters = [
            'status' => 'active',
            'minPrice' => 100,
            'maxPrice' => 500,
        ];

        $this->parameterBag->setAll($parameters);

        $this->assertSame($parameters, $this->parameterBag->all());
    }

    public function testClearParameters(): void
    {
        $this->parameterBag->setAll([
            'status' => 'active',
            'price' => 100,
        ]);

        $this->assertSame(2, $this->parameterBag->count());

        $this->parameterBag->clear();

        $this->assertSame(0, $this->parameterBag->count());
        $this->assertFalse($this->parameterBag->has('status'));
        $this->assertFalse($this->parameterBag->has('price'));
    }

    public function testClearReturnsFluentInterface(): void
    {
        $result = $this->parameterBag->clear();

        $this->assertSame($this->parameterBag, $result);
    }

    public function testCount(): void
    {
        $this->assertSame(0, $this->parameterBag->count());

        $this->parameterBag->set('status', 'active');
        $this->assertSame(1, $this->parameterBag->count());

        $this->parameterBag->set('price', 100);
        $this->assertSame(2, $this->parameterBag->count());

        $this->parameterBag->remove('status');
        $this->assertSame(1, $this->parameterBag->count());
    }

    public function testSetParameterWithDifferentTypes(): void
    {
        $this->parameterBag->set('string', 'value');
        $this->parameterBag->set('int', 123);
        $this->parameterBag->set('float', 123.45);
        $this->parameterBag->set('bool', true);
        $this->parameterBag->set('nullValue', null);
        $this->parameterBag->set('array', [1, 2, 3]);

        $this->assertSame('value', $this->parameterBag->get('string'));
        $this->assertSame(123, $this->parameterBag->get('int'));
        $this->assertSame(123.45, $this->parameterBag->get('float'));
        $this->assertTrue($this->parameterBag->get('bool'));
        $this->assertNull($this->parameterBag->get('nullValue'));
        $this->assertSame([1, 2, 3], $this->parameterBag->get('array'));
    }

    public function testSetEmptyParameterNameThrowsException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessage('Parameter name cannot be empty');

        $this->parameterBag->set('', 'value');
    }

    public function testSetInvalidParameterNameThrowsException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessageMatches('/Invalid parameter name/');

        $this->parameterBag->set('invalid-name', 'value');
    }

    public function testSetParameterNameWithSpacesThrowsException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessageMatches('/Invalid parameter name/');

        $this->parameterBag->set('invalid name', 'value');
    }

    public function testSetParameterNameStartingWithNumberThrowsException(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessageMatches('/Invalid parameter name/');

        $this->parameterBag->set('1invalid', 'value');
    }

    public function testValidParameterNames(): void
    {
        // These should all be valid
        $validNames = [
            'status',
            'minPrice',
            'max_price',
            '_private',
            '__special',
            'param123',
            'UPPER_CASE',
        ];

        foreach ($validNames as $name) {
            $this->parameterBag->set($name, 'test');
            $this->assertTrue($this->parameterBag->has($name), "Parameter name '{$name}' should be valid");
        }
    }

    public function testOverwriteExistingParameter(): void
    {
        $this->parameterBag->set('status', 'active');
        $this->assertSame('active', $this->parameterBag->get('status'));

        $this->parameterBag->set('status', 'inactive');
        $this->assertSame('inactive', $this->parameterBag->get('status'));
    }

    public function testFluentChaining(): void
    {
        $result = $this->parameterBag
            ->set('status', 'active')
            ->set('minPrice', 100)
            ->set('maxPrice', 500);

        $this->assertSame($this->parameterBag, $result);
        $this->assertSame(3, $this->parameterBag->count());
    }

    public function testGetNonExistentParameterShowsAvailableParameters(): void
    {
        $this->parameterBag->setAll([
            'status' => 'active',
            'price' => 100,
        ]);

        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessageMatches('/:status, :price/');

        $this->parameterBag->get('nonexistent');
    }

    public function testGetNonExistentParameterWhenEmptyShowsNone(): void
    {
        $this->expectException(InvalidParameterException::class);
        $this->expectExceptionMessageMatches('/Available parameters: none/');

        $this->parameterBag->get('nonexistent');
    }
}
