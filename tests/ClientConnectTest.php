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
use Amp\Socket\ConnectException;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Config;
use PHPUnit\Framework\TestCase;

class ClientConnectTest extends TestCase
{
    public function testConnect(): void
    {
        Loop::run(
            function()
            {
                $client = new Client(
                    Config::parse(\getenv('RIDGE_TEST_DSN'))
                );

                yield $client->connect();

                self::assertTrue($client->isConnected());

                yield $client->disconnect();
            }
        );
    }

    public function testConnectFailure(): void
    {
        $this->expectException(ConnectException::class);

        Loop::run(
            function()
            {
                $client = Client::create('amqp://127.0.0.2:5673');

                yield $client->connect();
            }
        );
    }

    public function testConnectAuth()
    {
        $this->expectException(ConnectException::class);

        Loop::run(
            function()
            {
                $client = new Client(new Config('localhost', 5673, 'testuser', 'testpassword'));

                yield $client->connect();
                yield $client->disconnect();
            }
        );
    }
}
