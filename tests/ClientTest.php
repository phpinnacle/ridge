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

use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Message;

class ClientTest extends AsyncTest
{
    public function testOpenChannel(Client $client): \Generator
    {
        self::assertPromise($promise = $client->channel());
        self::assertInstanceOf(Channel::class, yield $promise);

        yield $client->disconnect();
    }

    public function testOpenMultipleChannel(Client $client): \Generator
    {
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
    }

    public function testDisconnectWithBufferedMessages(Client $client): \Generator
    {
        /** @var Channel $channel */
        $channel = yield $client->channel();
        $count   = 0;

        yield $channel->qos(0, 1000);
        yield $channel->queueDeclare('disconnect_test', false, false, false, true);
        yield $channel->consume(function (Message $message, Channel $channel) use ($client, &$count) {
            yield $channel->ack($message);

            self::assertEquals(1, ++$count);

            yield $client->disconnect();
        }, 'disconnect_test');

        yield $channel->publish('.', '', 'disconnect_test');
        yield $channel->publish('.', '', 'disconnect_test');
        yield $channel->publish('.', '', 'disconnect_test');
    }
}
