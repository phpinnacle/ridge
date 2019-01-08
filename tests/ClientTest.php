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

use Amp\Deferred;
use Amp\Loop;
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Message;

class ClientTest extends RidgeTest
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

    /**
     * @expectedException \Amp\Socket\ConnectException
     */
    public function testConnectFailure()
    {
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

    public function testOpenChannel()
    {
        self::loop(function (Client $client) {
            self::assertPromise($promise = $client->channel());
            self::assertInstanceOf(Channel::class, yield $promise);

            yield $client->disconnect();
        });
    }

    /**
     * @group failing
     */
    public function testOpenMultipleChannel()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel1 */
            /** @var Channel $channel2 */
            $channel1 = yield $client->channel();
            $channel2 = yield $client->channel();

            self::assertInstanceOf(Channel::class, $channel1);
            self::assertInstanceOf(Channel::class, $channel2);
            self::assertNotEquals($channel1->id(), $channel2->id());

            /** @var Channel $channel3 */
            $channel3 = yield $client->channel();

            self::assertInstanceOf(Channel::class, $channel3);
            self::assertNotEquals($channel1->id(), $channel3->id());
            self::assertNotEquals($channel2->id(), $channel3->id());

            yield $client->disconnect();
        });
    }

    public function testDisconnectWithBufferedMessages()
    {
        self::loop(function () {
            $client = self::client();
            $count  = 0;

            yield $client->connect();

            /** @var Channel $channel */
            $channel  = yield $client->channel();

            yield $channel->qos(0, 1000);
            yield $channel->queueDeclare('disconnect_test', false, false, false, true);
            yield $channel->consume(function (Message $message, Channel $channel) use ($client, &$count) {
                $channel->ack($message);

                self::assertEquals(1, ++$count);

                yield $client->disconnect();
            }, 'disconnect_test');

            yield $channel->publish('.', '', 'disconnect_test');
            yield $channel->publish('.', '', 'disconnect_test');
            yield $channel->publish('.', '', 'disconnect_test');
        });
    }
}
