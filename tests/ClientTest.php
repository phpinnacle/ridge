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
use PHPinnacle\Ridge\Config;
use PHPinnacle\Ridge\Message;
use PHPUnit\Framework\TestCase;
use function Amp\Promise\wait;

class ClientTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client(
            Config::parse(\getenv('RIDGE_TEST_DSN'))
        );

        wait($this->client->connect());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait($this->client->disconnect());
    }

    public function testOpenChannel(): void
    {
        self::assertInstanceOf(Channel::class, wait($this->client->channel()));
    }

    public function testOpenMultipleChannel(): void
    {
        /** @var Channel $channel1 */
        $channel1 = wait($this->client->channel());

        /** @var Channel $channel2 */
        $channel2 = wait($this->client->channel());

        self::assertInstanceOf(Channel::class, $channel1);
        self::assertInstanceOf(Channel::class, $channel2);
        self::assertNotEquals($channel1->id(), $channel2->id());

        /** @var Channel $channel3 */
        $channel3 = wait($this->client->channel());

        self::assertInstanceOf(Channel::class, $channel3);
        self::assertNotEquals($channel1->id(), $channel3->id());
        self::assertNotEquals($channel2->id(), $channel3->id());
    }

    public function testDisconnectWithBufferedMessages(): void
    {
        /** @var Channel $channel */
        $channel = wait($this->client->channel());


        wait($channel->qos(0, 1000));

        wait(
            $channel->queueDeclare('disconnect_test', false, false, false, true)
        );

        wait($channel->publish('.', '', 'disconnect_test'));
        wait($channel->publish('.', '', 'disconnect_test'));
        wait($channel->publish('.', '', 'disconnect_test'));

        $count = 0;

        wait(
            $channel->consume(
                function(Message $message, Channel $channel) use (&$count)
                {
                    yield $channel->ack($message);

                    $count++;

                    if($count === 3)
                    {
                        return;
                    }
                },
                'disconnect_test'
            )
        );

        self::assertSame(3, $count);
    }
}
