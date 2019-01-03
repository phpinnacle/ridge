<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPinnacle\Ridge\Tests;

use Amp\Loop;
use Amp\Promise;
use PHPUnit\Framework\TestCase;

abstract class RidgeTest extends TestCase
{
    /**
     * @param callable $callable
     *
     * @return void
     */
    public static function loop(callable $callable): void
    {
        Loop::run($callable);
    }

    /**
     * @param mixed $value
     *
     * @return void
     */
    public static function assertPromise($value): void
    {
        self::assertInstanceOf(Promise::class, $value);
    }

    /**
     * @param mixed $value
     *
     * @return void
     */
    public static function assertArray($value): void
    {
        self::assertInternalType('array', $value);
    }
}
