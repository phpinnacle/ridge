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

    public function testExchangeDeclare()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            $promise = $channel->exchangeDeclare("test_exchange", "direct", false, false, true);

            self::assertPromise($promise);
            self::assertFrame(Protocol\ExchangeDeclareOkFrame::class, yield $promise);

            yield $client->disconnect();
        });
    }

    public function testQueueDeclare()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            $promise = $channel->queueDeclare("test_queue", false, false, false, true);

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

            yield $channel->exchangeDeclare("test_exchange", "direct", false, false, true);
            yield $channel->queueDeclare("test_queue", false, false, false, true);

            $promise = $channel->queueBind("test_queue", "test_exchange");

            self::assertPromise($promise);
            self::assertFrame(Protocol\QueueBindOkFrame::class, yield $promise);

            yield $client->disconnect();
        });
    }

    public function testPublish()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            $promise = $channel->publish("test publish");

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

            yield $channel->queueDeclare("test_queue", false, false, false, true);

            yield $channel->publish("hi", "", "test_queue");

            yield $channel->consume(function (Message $message) use ($client) {
                self::assertEquals("hi", $message->content());

                yield $client->disconnect();
            }, "test_queue", false, true);
        });
    }

    public function testHeaders()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare("test_queue", false, false, false, true);

            yield $channel->publish("<b>hi html</b>", "", "test_queue", [
                "content-type" => "text/html",
            ]);

            yield $channel->consume(function (Message $message) use ($client) {
                self::assertEquals("text/html", $message->header("content-type"));
                self::assertEquals("<b>hi html</b>", $message->content());

                yield $client->disconnect();
            }, "test_queue", false, true);
        });
    }

    /**
     * @group failing
     */
    public function testBigMessage()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare("test_queue", false, false, false, true);

            $body = \str_repeat("a", 10 << 20); // 10 MiB

            yield $channel->publish($body, "", "test_queue");

            yield $channel->consume(function (Message $message, Channel $channel) use ($body, $client) {
                self::assertEquals($body, $message->content());

                yield $channel->ack($message);
                yield $client->disconnect();
            }, "test_queue");
        });
    }

//    public function testGet()
//    {
//        self::loop(function (Client $client) {
//            /** @var Channel $channel */
//            $channel = yield $client->channel();
//
//            yield $channel->queueDeclare("get_test", false, false, false, true);
//
//            yield $channel->publish(".", "", "get_test");
//
//            /** @var Message $message1 */
//            $message1 = yield $channel->get("get_test", true);
//
//            self::assertNotNull($message1);
//            self::assertInstanceOf(Message::class, $message1);
//            self::assertEquals($message1->exchange(), "");
//            self::assertEquals($message1->content(), ".");
//
//            $message2 = yield $channel->get("get_test", true);
//
//            self::assertNull($message2);
//
//            yield $channel->publish("..", "", "get_test");
//
//            $message3 = yield $channel->get("get_test", true);
//
//            $client->disconnect()->onResolve(function () use ($client) {
//                yield $client->connect();
//
//                $channel = yield $client->channel();
//
//                /** @var Message $message3 */
//                $message3 = yield $channel->get("get_test");
//
//                self::assertNotNull($message3);
//                self::assertInstanceOf(Message::class, $message3);
//                self::assertEquals($message3->exchange(), "");
//                self::assertEquals($message3->content(), "..");
//
//                yield $channel->ack($message3);
//
//                yield $client->disconnect();
//            });
//        });
//    }

    public function testEmptyMessage()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare("empty_body_message_test", false, false, false, true);
            yield $channel->publish("", "", "empty_body_message_test");

            $message = yield $channel->get("empty_body_message_test", true);

            self::assertNotNull($message);
            self::assertEquals("", $message->content());

            $count = 0;

            yield $channel->consume(function (Message $message, Channel $channel) use ($client, &$count) {
                self::assertEmpty($message->content());

                yield $channel->ack($message);

                if (++$count === 2) {
                    yield $client->disconnect();
                }
            }, "empty_body_message_test");

            yield $channel->publish("", "", "empty_body_message_test");
            yield $channel->publish("", "", "empty_body_message_test");
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
//        $channel->publish("xxx", [], "", "404", true);
//
//        $client->run(1);
//
//        self::assertNotNull($returnedMessage);
//        self::assertInstanceOf(Message::class, $returnedMessage);
//        self::assertEquals("xxx", $returnedMessage->content);
//        self::assertEquals("", $returnedMessage->exchange);
//        self::assertEquals("404", $returnedMessage->routingKey);
//    }

    public function testTxs()
    {
        self::loop(function (Client $client) {
            /** @var Channel $channel */
            $channel = yield $client->channel();

            yield $channel->queueDeclare("tx_test", false, false, false, true);

            yield $channel->txSelect();
            yield $channel->publish(".", "", "tx_test");
            yield $channel->txCommit();

            $message = yield $channel->get("tx_test", true);

            self::assertNotNull($message);
            self::assertInstanceOf(Message::class, $message);
            self::assertEquals(".", $message->content());

            $channel->publish("..", "", "tx_test");
            $channel->txRollback();

            $nothing = yield $channel->get("tx_test", true);

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

            yield $channel->txSelect();
            yield $channel->txSelect();

            yield $client->disconnect();
        });
    }

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
//        $deliveryTag = $channel->publish(".");
//
//        $client->run(1);
//
//        self::assertNull($deliveryTag);
//    }
}
