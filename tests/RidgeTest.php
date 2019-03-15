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

use Amp\Promise;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Config;
use PHPUnit\Framework\TestCase;

abstract class RidgeTest extends TestCase
{
    /**
     * @param mixed $value
     *
     * @return void
     */
    protected static function assertPromise($value): void
    {
        self::assertInstanceOf(Promise::class, $value);
    }

    /**
     * @param string $class
     * @param mixed  $value
     *
     * @return void
     */
    protected static function assertFrame(string $class, $value): void
    {
        self::assertInstanceOf($class, $value);
    }

    /**
     * @return Client
     */
    protected static function client(): Client
    {
        if (!$dsn = \getenv('RIDGE_TEST_DSN')) {
            self::markTestSkipped('No test dsn! Please set RIDGE_TEST_DSN environment variable.');
        }

        $config = Config::parse($dsn);
        $config->heartbeat(0);

        return new Client($config);
    }
}
