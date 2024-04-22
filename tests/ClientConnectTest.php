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
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Message;

class ClientConnectTest extends RidgeTestCase
{
    public function testConnect()
    {
        Loop::run(function () {
            $client = self::client();

            $promise = $client->connect();

            self::assertPromise($promise);

            yield $promise;

            self::assertTrue($client->isConnected());

            yield $client->disconnect();
        });
    }

    public function testConnectFailure()
    {
        self::expectException(ConnectException::class);

        Loop::run(function () {
            $client = Client::create('amqp://127.0.0.2:5673');

            yield $client->connect();
        });
    }
//
//    public function testConnectAuth()
//    {
//        $client = new Client([
//            'user' => 'testuser',
//            'password' => 'testpassword',
//            'vhost' => 'testvhost',
//        ]);
//        $client->connect();
//        $client->disconnect();
//
//        $this->assertTrue(true);
//    }
}
