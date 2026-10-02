<?php

namespace Tests\Unit;

use App\Support\Js;
use App\Support\ObjectId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function truthiness(): array
    {
        return [
            'null' => [null, false],
            'false' => [false, false],
            'zero' => [0, false],
            'zero float' => [0.0, false],
            'NaN' => [NAN, false],
            'empty string' => ['', false],
            'string zero' => ['0', true],
            'string false' => ['false', true],
            'empty array' => [[], true],
            'number' => [5, true],
        ];
    }

    #[DataProvider('truthiness')]
    public function test_truthy_follows_javascript(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, Js::truthy($value));
    }

    public function test_parse_int_follows_javascript(): void
    {
        $this->assertSame(2, Js::parseInt('2abc'));
        $this->assertSame(-3, Js::parseInt(' -3'));
        $this->assertSame(1, Js::parseInt('1.9'));
        $this->assertNull(Js::parseInt('abc'));
    }

    public function test_to_fixed_rounds_like_javascript(): void
    {
        $this->assertSame('1.00', Js::toFixed(1.005, 2));
        $this->assertSame('0.13', Js::toFixed(0.125, 2));
        $this->assertSame('15.99', Js::toFixed(19.99 - 19.99 * 20 / 100, 2));
        $this->assertSame('10.00', Js::toFixed(9.999, 2));
    }

    public function test_to_number_follows_javascript(): void
    {
        $this->assertSame(16, Js::toNumber('0x10'));
        $this->assertSame(0, Js::toNumber('  '));
        $this->assertSame(1.5, Js::toNumber('1.5'));
        $this->assertNull(Js::toNumber('12px'));
    }

    public function test_object_ids_are_valid_and_increasing(): void
    {
        $first = ObjectId::generate();
        $second = ObjectId::generate();

        $this->assertTrue(ObjectId::isValid($first));
        $this->assertGreaterThan($first, $second);
        $this->assertFalse(ObjectId::isValid('not-an-id'));
    }
}
