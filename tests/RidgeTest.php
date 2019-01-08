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

use function Amp\call;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\Promise;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Config;

abstract class RidgeTest extends TestCase
{
    /**
     * @param callable $callable
     *
     * @return void
     */
    public static function loop(callable $callable): void
    {
        Loop::run(function () use ($callable) {
            $client = self::client();

            yield $client->connect();

            yield call($callable, $client);

            $info  = Loop::getInfo();
            $count = $info['enabled_watchers']['referenced'];

            if ($count !== 0) {
                $message = "Still have {$count} loop watchers.";

                foreach (['defer', 'delay', 'repeat', 'on_readable', 'on_writable'] as $key) {
                    $message .= " {$key} - {$info[$key]['enabled']}.";
                }

                self::markTestIncomplete($message);

                Loop::stop();
            }
        });
    }

    /**
     * @return Client
     */
    public static function client(): Client
    {
        if (!$dsn = \getenv('RIDGE_TEST_DSN')) {
            self::markTestSkipped('No test dsn! Please set RIDGE_TEST_DSN environment variable.');
        }

        $config = Config::parse($dsn);
        $config->heartbeat(0);

        return new Client($config);
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
     * @param string $class
     * @param mixed  $value
     *
     * @return void
     */
    public static function assertFrame(string $class, $value): void
    {
        self::assertInstanceOf($class, $value);
    }

    /**
     * @param mixed $value
     *
     * @return void
     */
    public static function assertInteger($value): void
    {
        self::assertInternalType('int', $value);
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
