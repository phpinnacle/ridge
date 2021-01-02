<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPinnacle\Ridge\Tests;

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Exception;

class BufferTest extends RidgeTest
{
    public function testTimestamp(): void
    {
        $buffer = new Buffer;
        $date = new \DateTime;

        self::assertSame($buffer, $buffer->appendTimestamp($date));
        self::assertSame($date->getTimestamp(), $buffer->consumeTimestamp()->getTimestamp());
    }

    public function testString(): void
    {
        $buffer = new Buffer;

        self::assertSame($buffer, $buffer->appendString('abcd'));
        self::assertSame('abcd', $buffer->consumeString());
    }

    public function testText(): void
    {
        $buffer = new Buffer;

        self::assertSame($buffer, $buffer->appendText('abcd'));
        self::assertSame('abcd', $buffer->consumeText());
    }

    public function testArray(): void
    {
        $buffer = new Buffer;
        $array = [1, 'a', null, true, M_PI, [2], ['a' => 'b'], \DateTime::createFromFormat('m/d/Y', '1/1/1970')];

        self::assertSame($buffer, $buffer->appendArray($array));
        self::assertEquals($array, $buffer->consumeArray());
    }

    public function testArrayWithUnknownField(): void
    {
        $this->expectException(Exception\ProtocolException::class);

        $buffer = new Buffer;
        $table = [
            1,
            'a',
            new \stdClass(),
        ];

        $buffer->appendArray($table);
    }

    public function testTable(): void
    {
        $buffer = new Buffer;
        $table = [
            '1' => 1,
            'b' => 'a',
            'c' => null,
            '5' => true,
            'p' => M_PI,
            '6' => [1,2],
            'g' => [
                'a' => 1
            ],
        ];

        self::assertSame($buffer, $buffer->appendTable($table));
        self::assertEquals($table, $buffer->consumeTable());
    }

    public function testTableWithUnknownField(): void
    {
        $this->expectException(Exception\ProtocolException::class);

        $buffer = new Buffer;
        $table = [
            '1' => 1,
            'b' => 'a',
            'c' => new \stdClass(),
        ];

        $buffer->appendTable($table);
    }

    public function testBits(): void
    {
        $buffer = new Buffer;
        $bits = [true, false, true];

        self::assertSame($buffer, $buffer->appendBits($bits));
        self::assertEquals($bits, $buffer->consumeBits(\count($bits)));
    }
}
