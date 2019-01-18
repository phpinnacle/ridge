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
use PHPinnacle\Ridge\Protocol;

class ChannelTest extends RidgeTest
{
    /**
     * @expectedException \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function testOpenNotReadyChannel()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            try {
                yield $channel->open();
            } finally {
                yield $client->disconnect();
            }
        });
    }

    public function testClose()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();
            
            $promise = $channel->close();
            
            self::assertPromise($promise);
            
            yield $promise;
            
            yield $client->disconnect();
        });
    }

    /**
     * @expectedException \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function testCloseAlreadyClosedChannel()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();
            
            try {
                yield $channel->close();
                yield $channel->close();
            } finally {
                yield $client->disconnect();
            }
        });
    }

    public function testExchangeDeclare()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            $promise = $channel->exchangeDeclare('test_exchange', 'direct', false, false, true);

            self::assertPromise($promise);
            self::assertFrame(Protocol\ExchangeDeclareOkFrame::class, yield $promise);

            yield $client->disconnect();
        });
    }

    public function testExchangeDelete()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();
            
            yield $channel->exchangeDeclare('test_exchange_no_ad', 'direct');

            $promise = $channel->exchangeDelete('test_exchange_no_ad');

            self::assertPromise($promise);
            self::assertFrame(Protocol\ExchangeDeleteOkFrame::class, yield $promise);
            
            yield $client->disconnect();
        });
    }

    public function testQueueDeclare()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            $promise = $channel->queueDeclare('test_queue', false, false, false, true);

            self::assertPromise($promise);
            self::assertFrame(Protocol\QueueDeclareOkFrame::class, yield $promise);

            yield $client->disconnect();
        });
    }

    public function testQueueBind()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->exchangeDeclare('test_exchange', 'direct', false, false, true);
            yield $channel->queueDeclare('test_queue', false, false, false, true);

            $promise = $channel->queueBind('test_queue', 'test_exchange');

            self::assertPromise($promise);
            self::assertFrame(Protocol\QueueBindOkFrame::class, yield $promise);

            yield $client->disconnect();
        });
    }

    public function testQueueUnbind()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();
            
            yield $channel->exchangeDeclare('test_exchange', 'direct', false, false, true);
            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->queueBind('test_queue', 'test_exchange');
            
            $promise = $channel->queueUnbind('test_queue', 'test_exchange');
            
            self::assertPromise($promise);
            self::assertFrame(Protocol\QueueUnbindOkFrame::class, yield $promise);
            
            yield $client->disconnect();
        });
    }

    public function testQueuePurge()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();
            
            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('test', '', 'test_queue');
            yield $channel->publish('test', '', 'test_queue');
    
            $promise = $channel->queuePurge('test_queue');
            
            /** @var Protocol\QueuePurgeOkFrame $frame */
            $frame = yield $promise;
            
            self::assertPromise($promise);
            self::assertFrame(Protocol\QueuePurgeOkFrame::class, $frame);
            self::assertEquals(2, $frame->messageCount);
            
            yield $client->disconnect();
        });
    }

    public function testQueueDelete()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();
            
            yield $channel->queueDeclare('test_queue_no_ad');
            yield $channel->publish('test', '', 'test_queue_no_ad');

            $promise = $channel->queueDelete('test_queue_no_ad');
    
            /** @var Protocol\QueueDeleteOkFrame $frame */
            $frame = yield $promise;

            self::assertPromise($promise);
            self::assertFrame(Protocol\QueueDeleteOkFrame::class, $frame);
            self::assertEquals(1, $frame->messageCount);
            
            yield $client->disconnect();
        });
    }

    public function testPublish()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            $promise = $channel->publish('test publish');

            self::assertPromise($promise);
            self::assertEquals(1, yield $promise);

            yield $client->disconnect();
        });
    }

    public function testConsume()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('hi', '', 'test_queue');
    
            /** @var Protocol\BasicConsumeOkFrame $frame */
            $frame = yield $channel->consume(function (Message $message) use ($client, &$frame) {
                self::assertEquals('hi', $message->content());
                self::assertEquals($frame->consumerTag, $message->consumerTag());

                yield $client->disconnect();
            }, 'test_queue', false, true);
        });
    }

    public function testCancel()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('hi', '', 'test_queue');

            /** @var Protocol\BasicConsumeOkFrame $consume */
            $consume = yield $channel->consume(function (Message $message) {
            }, 'test_queue', false, true);

            /** @var Protocol\BasicCancelOkFrame $cancel */
            $promise = $channel->cancel($consume->consumerTag);
            $cancel = yield $promise;

            self::assertPromise($promise);
            self::assertFrame(Protocol\BasicCancelOkFrame::class, $cancel);
            self::assertEquals($consume->consumerTag, $cancel->consumerTag);

            yield $client->disconnect();
        });
    }

    public function testHeaders()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('<b>hi html</b>', '', 'test_queue', [
                'content-type' => 'text/html',
                'custom' => 'value',
            ]);

            yield $channel->consume(function (Message $message) use ($client) {
                self::assertEquals('text/html', $message->header('content-type'));
                self::assertEquals('value', $message->header('custom'));
                self::assertEquals('<b>hi html</b>', $message->content());

                yield $client->disconnect();
            }, 'test_queue', false, true);
        });
    }

    public function testGet()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('get_test', false, false, false, true);

            yield $channel->publish('.', '', 'get_test');

            /** @var Message $message1 */
            $message1 = yield $channel->get('get_test', true);

            self::assertNotNull($message1);
            self::assertInstanceOf(Message::class, $message1);
            self::assertEquals('', $message1->exchange());
            self::assertEquals('.', $message1->content());
            self::assertEquals('get_test', $message1->routingKey());
            self::assertEquals(1, $message1->deliveryTag());
            self::assertNull($message1->consumerTag());
            self::assertFalse($message1->redelivered());
            self::assertArray($message1->headers());

            $message2 = yield $channel->get('get_test', true);

            self::assertNull($message2);

            $deliveryTag = yield $channel->publish('..', '', 'get_test');
    
            /** @var Message $message3 */
            $message3 = yield $channel->get('get_test');

            self::assertNotNull($message3);
            self::assertEquals($deliveryTag, $message3->deliveryTag());
            self::assertFalse($message3->redelivered());

            $client->disconnect()->onResolve(function () use ($client) {
                yield $client->connect();

                /** @var Channel $channel */
                $channel = yield $client->channel();

                /** @var Message $message3 */
                $message3 = yield $channel->get('get_test');

                self::assertNotNull($message3);
                self::assertInstanceOf(Message::class, $message3);
                self::assertEquals('', $message3->exchange());
                self::assertEquals('..', $message3->content());
                self::assertTrue($message3->redelivered());

                yield $channel->ack($message3);

                yield $client->disconnect();
            });
        });
    }

    public function testAck()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('.', '', 'test_queue');

            /** @var Message $message */
            $message = yield $channel->get('test_queue');
            $promise = $channel->ack($message);

            self::assertPromise($promise);
            self::assertTrue(yield $promise);

            yield $client->disconnect();
        });
    }

    public function testNack()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('.', '', 'test_queue');

            /** @var Message $message */
            $message = yield $channel->get('test_queue');

            self::assertNotNull($message);
            self::assertFalse($message->redelivered());

            $promise = $channel->nack($message);

            self::assertPromise($promise);
            self::assertTrue(yield $promise);

            /** @var Message $message */
            $message = yield $channel->get('test_queue');

            self::assertNotNull($message);
            self::assertTrue($message->redelivered());

            $promise = $channel->nack($message, false, false);

            self::assertPromise($promise);
            self::assertTrue(yield $promise);

            self::assertNull(yield $channel->get('test_queue'));

            yield $client->disconnect();
        });
    }

    public function testReject()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('.', '', 'test_queue');

            /** @var Message $message */
            $message = yield $channel->get('test_queue');

            self::assertNotNull($message);
            self::assertFalse($message->redelivered());

            $promise = $channel->reject($message);

            self::assertPromise($promise);
            self::assertTrue(yield $promise);

            /** @var Message $message */
            $message = yield $channel->get('test_queue');

            self::assertNotNull($message);
            self::assertTrue($message->redelivered());

            $promise = $channel->reject($message, false);

            self::assertPromise($promise);
            self::assertTrue(yield $promise);

            self::assertNull(yield $channel->get('test_queue'));

            yield $client->disconnect();
        });
    }

    public function testRecover()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);
            yield $channel->publish('.', '', 'test_queue');

            /** @var Message $message */
            $message = yield $channel->get('test_queue');

            self::assertNotNull($message);
            self::assertFalse($message->redelivered());

            $promise = $channel->recover(true);

            self::assertPromise($promise);
            self::assertFrame(Protocol\BasicRecoverOkFrame::class, yield $promise);

            /** @var Message $message */
            $message = yield $channel->get('test_queue');

            self::assertNotNull($message);
            self::assertTrue($message->redelivered());

            yield $channel->ack($message);

            yield $client->disconnect();
        });
    }

    public function testBigMessage()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('test_queue', false, false, false, true);

            $body = \str_repeat('a', 10 << 20); // 10 MiB

            yield $channel->publish($body, '', 'test_queue');

            yield $channel->consume(function (Message $message, Channel $channel) use ($body, $client) {
                self::assertEquals(\strlen($body), \strlen($message->content()));

                yield $channel->ack($message);
                yield $client->disconnect();
            }, 'test_queue');
        });
    }

    /**
     * @expectedException \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function testGetDouble()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();
        
            yield $channel->queueDeclare('get_test_double', false, false, false, true);
            yield $channel->publish('.', '', 'get_test_double');

            try {
                yield [
                    $channel->get('get_test_double'),
                    $channel->get('get_test_double'),
                ];
            } finally {
                yield $channel->queueDelete('get_test_double');
    
                yield $client->disconnect();
            }
        });
    }

    public function testEmptyMessage()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('empty_body_message_test', false, false, false, true);
            yield $channel->publish('', '', 'empty_body_message_test');

            /** @var Message $message */
            $message = yield $channel->get('empty_body_message_test', true);

            self::assertNotNull($message);
            self::assertEquals('', $message->content());

            $count = 0;

            yield $channel->consume(function (Message $message, Channel $channel) use ($client, &$count) {
                self::assertEmpty($message->content());

                yield $channel->ack($message);

                if (++$count === 2) {
                    yield $client->disconnect();
                }
            }, 'empty_body_message_test');

            yield $channel->publish('', '', 'empty_body_message_test');
            yield $channel->publish('', '', 'empty_body_message_test');
        });
    }

    public function testTxs()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare('tx_test', false, false, false, true);

            yield $channel->txSelect();
            yield $channel->publish('.', '', 'tx_test');
            yield $channel->txCommit();

            /** @var Message $message */
            $message = yield $channel->get('tx_test', true);

            self::assertNotNull($message);
            self::assertInstanceOf(Message::class, $message);
            self::assertEquals('.', $message->content());

            $channel->publish('..', '', 'tx_test');
            $channel->txRollback();

            $nothing = yield $channel->get('tx_test', true);

            self::assertNull($nothing);

            yield $client->disconnect();
        });
    }

    /**
     * @expectedException \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function testTxSelectCannotBeCalledMultipleTimes()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            try {
                yield $channel->txSelect();
                yield $channel->txSelect();
            } finally {
                yield $client->disconnect();
            }
        });
    }
    
    /**
     * @expectedException \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function testTxCommitCannotBeCalledUnderNotTransactionMode()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            try {
                yield $channel->txCommit();
            } finally {
                yield $client->disconnect();
            }
        });
    }
    
    /**
     * @expectedException \PHPinnacle\Ridge\Exception\ChannelException
     */
    public function testTxRollbackCannotBeCalledUnderNotTransactionMode()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            try {
                yield $channel->txRollback();
            } finally {
                yield $client->disconnect();
            }
        });
    }
//
//    public function testReturn()
//    {
//        $client = new Client();
//        $client->connect();
//        $channel  = $client->channel();
//
//        /** @var Message $returnedMessage */
//        $returnedMessage = null;
//        /** @var MethodBasicReturnFrame $returnedFrame */
//        $returnedFrame = null;
//        $channel->addReturnListener(function (Message $message, MethodBasicReturnFrame $frame) use ($client, &$returnedMessage, &$returnedFrame) {
//            $returnedMessage = $message;
//            $returnedFrame = $frame;
//            $client->stop();
//        });
//
//        $channel->publish('xxx', [], '', '404', true);
//
//        $client->run(1);
//
//        self::assertNotNull($returnedMessage);
//        self::assertInstanceOf(Message::class, $returnedMessage);
//        self::assertEquals('xxx', $returnedMessage->content);
//        self::assertEquals('', $returnedMessage->exchange);
//        self::assertEquals('404', $returnedMessage->routingKey);
//    }

//    public function testConfirmMode()
//    {
//        $client = new Client();
//        $client->connect();
//        $channel = $client->channel();
//
//        $deliveryTag = null;
//        $channel->confirmSelect(function (MethodBasicAckFrame $frame) use (&$deliveryTag, $client) {
//            if ($frame->deliveryTag === $deliveryTag) {
//                $deliveryTag = null;
//                $client->stop();
//            }
//        });
//
//        $deliveryTag = $channel->publish('.');
//
//        $client->run(1);
//
//        self::assertNull($deliveryTag);
//    }
}
