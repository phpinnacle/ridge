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
use function Amp\Promise\wait;

class ClientConnectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Loop::run();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Loop::stop();
    }

    public function testConnect(): void
    {
        $client = new Client(
            Config::parse(\getenv('RIDGE_TEST_DSN'))
        );

        wait($client->connect());

        self::assertTrue($client->isConnected());

        wait($client->disconnect());
    }

    public function testConnectFailure(): void
    {
        $this->expectException(ConnectException::class);

        $client = Client::create('amqp://127.0.0.2:5673');

        wait($client->connect());
    }

    public function testConnectAuth()
    {
        $this->expectException(ConnectException::class);

        $client = new Client(new Config('localhost', 5673, 'testuser', 'testpassword'));

        wait($client->connect());
        wait($client->disconnect());
    }
}
