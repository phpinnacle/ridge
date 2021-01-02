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

use Amp\Deferred;
use Amp\Loop;
use PHPinnacle\Ridge\Channel;
use PHPinnacle\Ridge\Client;
use PHPinnacle\Ridge\Config;
use PHPinnacle\Ridge\Exception;
use PHPinnacle\Ridge\Message;
use PHPinnacle\Ridge\Queue;
use PHPUnit\Framework\TestCase;
use function Amp\call;
use function Amp\Promise\wait;

class ChannelTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Channel
     */
    private $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client(
            Config::parse(\getenv('RIDGE_TEST_DSN'))
        );

        wait($this->client->connect());

        $this->channel = wait($this->client->channel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        try
        {
            wait($this->channel->close());
        }
        catch(\Throwable)
        {

        }

        wait($this->client->disconnect());
    }

    public function testOpenNotReadyChannel(): void
    {
        /** @var Channel $channel */
        $channel = wait($this->client->channel());

        $this->expectException(Exception\ChannelException::class);

        wait($channel->open());
    }

    public function testCloseAlreadyClosedChannel(): void
    {
        /** @var Channel $channel */
        $channel = wait($this->client->channel());

        $this->expectException(Exception\ChannelException::class);

        wait($channel->close());
        wait($channel->close());
    }

    public function testExchangeDeclare(): void
    {
        wait(
            $this->channel->exchangeDeclare(
                'test_exchange',
                'direct',
                false,
                false,
                true
            )
        );
    }

    public function testExchangeDelete(): void
    {
        wait($this->channel->exchangeDeclare('test_exchange_no_ad'));
        wait($this->channel->exchangeDelete('test_exchange_no_ad'));
    }

    public function testQueueDeclare(): void
    {
        /** @var Queue $queue */
        $queue = wait(
            $this->channel->queueDeclare(
                'test_queue',
                false,
                false,
                false,
                true
            )
        );

        self::assertInstanceOf(Queue::class, $queue);
        self::assertSame('test_queue', $queue->name());
        self::assertSame(0, $queue->messages());
        self::assertSame(0, $queue->consumers());
    }

    public function testQueueBind(): void
    {
        wait(
            $this->channel->exchangeDeclare(
                'test_exchange',
                'direct',
                false,
                false,
                true
            )
        );

        wait(
            $this->channel->queueDeclare(
                'test_queue',
                false,
                false,
                false,
                true
            )
        );

        wait($this->channel->queueBind('test_queue', 'test_exchange'));
    }

    public function testQueueUnbind(): void
    {
        wait(
            $this->channel->exchangeDeclare(
                'test_exchange',
                'direct',
                false,
                false,
                true
            )
        );

        wait(
            $this->channel->queueDeclare(
                'test_queue',
                false,
                false,
                false,
                true
            )
        );

        wait($this->channel->queueBind('test_queue', 'test_exchange'));
        wait($this->channel->queueUnbind('test_queue', 'test_exchange'));
    }

    public function testQueuePurge(): void
    {
        wait($this->channel->queueDeclare('test_queue', false, false, false, true));
        wait($this->channel->publish('test', '', 'test_queue'));
        wait($this->channel->publish('test', '', 'test_queue'));

        $messages = wait($this->channel->queuePurge('test_queue'));

        self::assertEquals(2, $messages);
    }

    public function testQueueDelete(): void
    {
        wait($this->channel->queueDeclare('test_queue_no_ad'));
        wait($this->channel->publish('test', '', 'test_queue_no_ad'));

        $messages = wait($this->channel->queueDelete('test_queue_no_ad'));

        self::assertEquals(1, $messages);
    }

    public function testPublish(): void
    {
        self::assertNull(wait($this->channel->publish('test publish')));
    }

    public function testMandatoryPublish(): void
    {
        $deferred = new Deferred();
        $watcher  = Loop::delay(100, function() use ($deferred)
        {
            $deferred->resolve(false);
        });

        $this->channel->events()->onReturn(
            function(Message $message) use ($deferred, $watcher)
            {
                self::assertSame($message->content(), '.');
                self::assertSame($message->exchange(), '');
                self::assertSame($message->routingKey(), '404');
                self::assertSame($message->headers(), []);
                self::assertNull($message->consumerTag());
                self::assertNull($message->deliveryTag());
                self::assertFalse($message->redelivered());
                self::assertTrue($message->returned());

                Loop::cancel($watcher);

                $deferred->resolve(true);
            }
        );

        wait($this->channel->publish('.', '', '404', [], true));

        self::assertTrue(wait($deferred->promise()), 'Mandatory return event not received!');
    }

    public function testImmediatePublish(): void
    {
        $properties = $this->client->properties();

        // RabbitMQ 3 doesn't support "immediate" publish flag.
        if($properties->product() === 'RabbitMQ' && version_compare($properties->version(), '3.0', '>'))
        {
            return;
        }

        $deferred = new Deferred();
        $watcher  = Loop::delay(100, function() use ($deferred)
        {
            $deferred->resolve(false);
        });

        $this->channel->events()->onReturn(
            function(Message $message) use ($deferred, $watcher)
            {
                self::assertTrue($message->returned());

                Loop::cancel($watcher);

                $deferred->resolve(true);
            }
        );

        wait(
            $this->channel->queueDeclare('test_queue', false, false, false, true)
        );

        wait($this->channel->publish('.', '', 'test_queue', [], false, true));

        self::assertTrue(wait($deferred->promise()), 'Immediate return event not received!');
    }

    public function testConsume(): void
    {
        wait(
            $this->channel->queueDeclare(
                'test_queue',
                false,
                false,
                false,
                true
            )
        );

        wait($this->channel->publish('hi', '', 'test_queue'));

        wait(
            $this->channel->consume(
                function(Message $message) use (&$tag)
                {
                    self::assertEquals('hi', $message->content());
                    self::assertEquals($tag, $message->consumerTag());
                },
                'test_queue',
                false,
                true
            )
        );
    }

    public function testCancel(): void
    {
        wait(
            $this->channel->queueDeclare(
                'test_queue',
                false,
                false,
                false,
                true
            )
        );

        wait($this->channel->publish('hi', '', 'test_queue'));

        $tag = wait(
            $this->channel->consume(
                static function(Message $message)
                {
                },
                'test_queue',
                false,
                true
            )
        );

        wait($this->channel->cancel($tag));
    }

    public function testHeaders(): void
    {
        wait(
            $this->channel->queueDeclare(
                'test_queue',
                false,
                false,
                false,
                true
            )
        );

        wait(
            $this->channel->publish('<b>hi html</b>', '', 'test_queue', [
                'content-type' => 'text/html',
                'custom'       => 'value',
            ])
        );

        wait(
            $this->channel->consume(
                function(Message $message)
                {
                    self::assertEquals('text/html', $message->header('content-type'));
                    self::assertEquals('value', $message->header('custom'));
                    self::assertEquals('<b>hi html</b>', $message->content());

                },
                'test_queue',
                false,
                true
            )
        );
    }

    public function testGet(): void
    {
        wait(
            $this->channel->queueDeclare('get_test', false, false, false, true)
        );

        wait($this->channel->publish('.', '', 'get_test'));

        /** @var Message $message1 */
        $message1 = wait($this->channel->get('get_test', true));

        self::assertNotNull($message1);
        self::assertInstanceOf(Message::class, $message1);
        self::assertEquals('', $message1->exchange());
        self::assertEquals('.', $message1->content());
        self::assertEquals('get_test', $message1->routingKey());
        self::assertEquals(1, $message1->deliveryTag());
        self::assertNull($message1->consumerTag());
        self::assertFalse($message1->redelivered());
        self::assertIsArray($message1->headers());

        self::assertNull(wait($this->channel->get('get_test', true)));

        wait($this->channel->publish('..', '', 'get_test'));

        /** @var Message $message2 */
        $message2 = wait($this->channel->get('get_test'));

        self::assertNotNull($message2);
        self::assertEquals(2, $message2->deliveryTag());
        self::assertFalse($message2->redelivered());

        $this->client->disconnect()->onResolve(
            function()
            {
                yield $this->client->connect();

                /** @var Channel $channel */
                $channel = yield $this->client->channel();

                /** @var Message $message3 */
                $message3 = yield $channel->get('get_test');

                self::assertNotNull($message3);
                self::assertInstanceOf(Message::class, $message3);
                self::assertEquals('', $message3->exchange());
                self::assertEquals('..', $message3->content());
                self::assertTrue($message3->redelivered());

                yield $channel->ack($message3);

                yield $this->client->disconnect();
            }
        );
    }

    public function testAck(): void
    {
        wait(
            $this->channel->queueDeclare('test_queue', false, false, false, true)
        );
        wait($this->channel->publish('.', '', 'test_queue'));

        /** @var Message $message */
        $message = wait($this->channel->get('test_queue'));

        wait($this->channel->ack($message));
    }

    public function testNack(): void
    {
        wait(
            $this->channel->queueDeclare('test_queue', false, false, false, true)
        );

        wait($this->channel->publish('.', '', 'test_queue'));

        /** @var Message $message */
        $message = wait($this->channel->get('test_queue'));

        self::assertNotNull($message);
        self::assertFalse($message->redelivered());

        wait($this->channel->nack($message));

        /** @var Message $message */
        $message = wait($this->channel->get('test_queue'));

        self::assertNotNull($message);
        self::assertTrue($message->redelivered());

        wait($this->channel->nack($message, false, false));

        self::assertNull(wait($this->channel->get('test_queue')));

    }

    public function testReject(): void
    {
        wait(
            $this->channel->queueDeclare('test_queue', false, false, false, true)
        );

        wait($this->channel->publish('.', '', 'test_queue'));

        /** @var Message $message */
        $message = wait($this->channel->get('test_queue'));

        self::assertNotNull($message);
        self::assertFalse($message->redelivered());

        wait($this->channel->reject($message));

        /** @var Message $message */
        $message = wait($this->channel->get('test_queue'));

        self::assertNotNull($message);
        self::assertTrue($message->redelivered());

        wait($this->channel->reject($message, false));

        self::assertNull(wait($this->channel->get('test_queue')));
    }

    public function testRecover(): void
    {
        wait(
            $this->channel->queueDeclare('test_queue', false, false, false, true)
        );

        wait($this->channel->publish('.', '', 'test_queue'));

        /** @var Message $message */
        $message = wait($this->channel->get('test_queue'));

        self::assertNotNull($message);
        self::assertFalse($message->redelivered());

        wait($this->channel->recover(true));

        /** @var Message $message */
        $message = wait($this->channel->get('test_queue'));

        self::assertNotNull($message);
        self::assertTrue($message->redelivered());

        wait($this->channel->ack($message));
    }

    public function testBigMessage(): void
    {
        wait(
            $this->channel->queueDeclare('test_queue', false, false, false, true)
        );

        $body = \str_repeat('a', 10 << 20); // 10 MiB

        wait($this->channel->publish($body, '', 'test_queue'));

        wait(
            $this->channel->consume(
                function(Message $message, Channel $channel) use ($body)
                {
                    self::assertEquals(\strlen($body), \strlen($message->content()));

                    yield $channel->ack($message);
                },
                'test_queue'
            )
        );
    }

    public function testGetDouble(): void
    {
        $this->expectException(Exception\ChannelException::class);

        wait(
            $this->channel->queueDeclare(
                'get_test_double',
                false,
                false,
                false,
                true
            )
        );

        wait($this->channel->publish('.', '', 'get_test_double'));

        try
        {
            wait(
                call(
                    function()
                    {
                        yield [
                            $this->channel->get('get_test_double'),
                            $this->channel->get('get_test_double'),
                        ];
                    }
                )
            );
        }
        finally
        {
            wait($this->channel->queueDelete('get_test_double'));
        }
    }

    public function testEmptyMessage(): void
    {
        wait(
            $this->channel->queueDeclare(
                'empty_body_message_test',
                false,
                false,
                false,
                true
            )
        );

        wait($this->channel->publish('', '', 'empty_body_message_test'));

        /** @var Message $message */
        $message = wait($this->channel->get('empty_body_message_test', true));

        self::assertNotNull($message);
        self::assertEquals('', $message->content());

        wait($this->channel->publish('', '', 'empty_body_message_test'));
        wait($this->channel->publish('', '', 'empty_body_message_test'));

        $count = 0;

        wait(
            $this->channel->consume(
                function(Message $message, Channel $channel) use (&$count)
                {
                    self::assertEmpty($message->content());

                    yield $channel->ack($message);

                    if(++$count === 2)
                    {
                        return;
                    }
                },
                'empty_body_message_test'
            )
        );

        self::assertSame(2, $count);
    }

    public function testTxs(): void
    {
        wait($this->channel->queueDeclare('tx_test', false, false, false, true));

        wait($this->channel->txSelect());
        wait($this->channel->publish('.', '', 'tx_test'));
        wait($this->channel->txCommit());

        /** @var Message $message */
        $message = wait($this->channel->get('tx_test', true));

        self::assertNotNull($message);
        self::assertInstanceOf(Message::class, $message);
        self::assertEquals('.', $message->content());

        wait($this->channel->publish('..', '', 'tx_test'));
        wait($this->channel->txRollback());

        $nothing = wait($this->channel->get('tx_test', true));

        self::assertNull($nothing);
    }

    public function testTxSelectCannotBeCalledMultipleTimes(): void
    {
        $this->expectException(Exception\ChannelException::class);

        wait($this->channel->txSelect());
        wait($this->channel->txSelect());
    }

    public function testTxCommitCannotBeCalledUnderNotTransactionMode(): void
    {
        $this->expectException(Exception\ChannelException::class);

        wait($this->channel->txCommit());
    }

    public function testTxRollbackCannotBeCalledUnderNotTransactionMode(): void
    {
        $this->expectException(Exception\ChannelException::class);

        wait($this->channel->txRollback());
    }

    public function testConfirmMode(): void
    {
        $this->channel->events()->onAck(
            function(int $deliveryTag, bool $multiple)
            {
                self::assertEquals(1, $deliveryTag);
                self::assertFalse($multiple);
            }
        );

        wait($this->channel->confirmSelect());

        $deliveryTag = wait($this->channel->publish('.'));

        self::assertEquals(1, $deliveryTag);
    }
}
