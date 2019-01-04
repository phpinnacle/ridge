<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace PHPinnacle\Ridge;

use function Amp\asyncCall;
use function Amp\call;
use Amp\Promise;

final class Channel
{
    const
        STATE_READY     = 1,
        STATE_OPEN      = 2,
        STATE_ERROR     = 3,
        STATE_CLOSING   = 4,
        STATE_CLOSED    = 5
    ;

    const
        MODE_REGULAR       = 1, // Regular AMQP guarantees of published messages delivery.
        MODE_TRANSACTIONAL = 2, // Messages are published after 'tx.commit'.
        MODE_CONFIRM       = 3  // Broker sends asynchronously 'basic.ack's for delivered messages.
    ;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    private $state = self::STATE_READY;

    /**
     * @var int
     */
    private $mode = self::MODE_REGULAR;

    /**
     * @var bool
     */
    private $consume = false;

    /**
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * @var int
     */
    private $deliveryTag = 0;

    /**
     * @var int
     */
    private $frameMax = 8192; // TODO tune on connection

    /**
     * @param int        $id
     * @param Connection $connection
     */
    public function __construct(int $id, Connection $connection)
    {
        $this->id         = $id;
        $this->connection = $connection;
    }

    /**
     * @param string $outOfBand
     *
     * @return Promise<void>
     */
    public function open(string $outOfBand = ''): Promise
    {
        return call(function () use ($outOfBand) {
            if ($this->state !== self::STATE_READY) {
                throw new Exception\ChannelException("Trying to open not ready channel #{$this->id}.");
            }

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(5 + \strlen($outOfBand))
                ->appendUint16(20)
                ->appendUint16(10)
                ->appendString($outOfBand)
                ->appendUint8(206)
            );

            yield $this->await(Protocol\ChannelOpenOkFrame::class);

            $this->state = self::STATE_OPEN;
        });
    }

    /**
     * Closes channel.
     *
     * Always returns a promise, because there can be outstanding messages to be processed.
     *
     * @param int    $replyCode
     * @param string $replyText
     * 
     * @return Promise<void>
     */
    public function close(int $replyCode = 0, string $replyText = ''): Promise
    {
        return call(function () use ($replyCode, $replyText) {
            if ($this->state === self::STATE_CLOSED) {
                throw new Exception\ChannelException("Trying to close already closed channel #{$this->id}.");
            }

            if ($this->state === self::STATE_CLOSING) {
                return;
            }

            $this->state = self::STATE_CLOSING;

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(11 + \strlen($replyText))
                ->appendUint16(20)
                ->appendUint16(40)
                ->appendInt16($replyCode)
                ->appendString($replyText)
                ->appendInt16(0)
                ->appendInt16(0)
                ->appendUint8(206)
            );

            yield $this->await(Protocol\ChannelCloseOkFrame::class);

            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(4)
                ->appendUint16(20)
                ->appendUint16(41)
                ->appendUint8(206)
            );

            $this->state = self::STATE_CLOSED;
        });
    }

    /**
     * @param string $realm
     * @param bool   $exclusive
     * @param bool   $passive
     * @param bool   $active
     * @param bool   $write
     * @param bool   $read
     *
     * @return Promise<Protocol\AccessRequestOkFrame>
     */
    public function accessRequest
    (
        string $realm = '/data',
        bool $exclusive = false,
        bool $passive = true,
        bool $active = true,
        bool $write = true,
        bool $read = true
    ): Promise
    {
        $flags = [$exclusive, $passive, $active, $write, $read];

        return call (function () use ($realm, $flags) {
            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(6 + \strlen($realm))
                ->appendUint16(30)
                ->appendUint16(10)
                ->appendString($realm)
                ->appendBits($flags)
                ->appendUint8(206)
            );

            return $this->await(Protocol\AccessRequestOkFrame::class);
        });
    }

    /**
     * @param int  $prefetchSize
     * @param int  $prefetchCount
     * @param bool $global
     *
     * @return Promise<Protocol\BasicQosOkFrame>
     */
    public function qos(int $prefetchSize = 0, int $prefetchCount = 0, bool $global = false): Promise
    {
        return call(function () use ($prefetchSize, $prefetchCount, $global) {
            yield $this->connection->write((new Buffer)
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(11)
                ->appendUint16(60)
                ->appendUint16(10)
                ->appendInt32($prefetchSize)
                ->appendInt16($prefetchCount)
                ->appendBits([$global])
                ->appendUint8(206)
            );

            return $this->await(Protocol\BasicQosOkFrame::class);
        });
    }

    /**
     * Creates new consumer on channel.
     *
     * @param callable $callback
     * @param string   $queue
     * @param string   $consumerTag
     * @param bool     $noLocal
     * @param bool     $noAck
     * @param bool     $exclusive
     * @param bool     $nowait
     * @param array    $arguments
     *
     * @return Promise<Protocol\BasicConsumeOkFrame>
     */
    public function consume
    (
        callable $callback,
        string $queue = '',
        string $consumerTag = '',
        bool $noLocal = false,
        bool $noAck = false,
        bool $exclusive = false,
        bool $nowait = false,
        array $arguments = []
    ) : Promise
    {
        $flags = [$noLocal, $noAck, $exclusive, $nowait];

        return call(function () use ($callback, $queue, $consumerTag, $flags, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(60)
                ->appendUint16(20)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendString($consumerTag)
                ->appendBits($flags)
                ->appendTable($arguments)
            ;

            yield $this->connection->send($this->methodFrame(60, 20, $buffer));

            /** @var Protocol\BasicConsumeOkFrame $frame */
            $frame = yield $this->await(Protocol\BasicConsumeOkFrame::class);

            $this->callbacks[$frame->consumerTag] = $callback;

            $this->startConsuming();

            return $frame;
        });
    }

    /**
     * Cancels given consumer subscription.
     *
     * @param string $consumerTag
     * @param bool   $nowait
     *
     * @return Promise<bool|Protocol\BasicCancelOkFrame>
     */
    public function cancel(string $consumerTag, bool $nowait = false): Promise
    {
        return call(function () use ($consumerTag, $nowait) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(6 + \strlen($consumerTag))
                ->appendUint16(60)
                ->appendUint16(30)
                ->appendString($consumerTag)
                ->appendBits([$nowait])
                ->appendUint8(206)
            ;

            $result = yield $this->connection->write($buffer);

            if ($nowait === false) {
                $result = yield $this->await(Protocol\BasicCancelOkFrame::class);
            }

            unset($this->callbacks[$consumerTag]);

            return $result;
        });
    }

    /**
     * Acks given message.
     *
     * @param Message $message
     * @param bool    $multiple
     *
     * @return Promise<boolean>
     */
    public function ack(Message $message, bool $multiple = false): Promise
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(13)
            ->appendUint16(60)
            ->appendUint16(80)
            ->appendInt64($message->deliveryTag())
            ->appendBits([$multiple])
            ->appendUint8(206)
        ;

        return $this->connection->write($buffer);
    }

    /**
     * Nacks given message.
     *
     * @param Message $message
     * @param bool    $multiple
     * @param bool    $requeue
     *
     * @return Promise<boolean>
     */
    public function nack(Message $message, bool $multiple = false, bool $requeue = true): Promise
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(13)
            ->appendUint16(60)
            ->appendUint16(120)
            ->appendInt64($message->deliveryTag())
            ->appendBits([$multiple, $requeue])
            ->appendUint8(206)
        ;

        return $this->connection->write($buffer);
    }

    /**
     * Rejects given message.
     *
     * @param Message $message
     * @param bool    $requeue
     *
     * @return Promise<boolean>
     */
    public function reject(Message $message, bool $requeue = true): Promise
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(13)
            ->appendUint16(60)
            ->appendUint16(90)
            ->appendInt64($message->deliveryTag())
            ->appendBits([$requeue])
            ->appendUint8(206)
        ;

        return $this->connection->write($buffer);
    }

    /**
     * @param bool $requeue
     *
     * @return Promise<bool>
     */
    public function recover(bool $requeue = false): Promise
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(5)
            ->appendUint16(60)
            ->appendUint16(110)
            ->appendBits([$requeue])
            ->appendUint8(206)
        ;

        return $this->connection->write($buffer);
    }

    /**
     * @param bool $requeue
     *
     * @return Promise<bool>
     */
    public function recoverAsync(bool $requeue = false): Promise
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(5)
            ->appendUint16(60)
            ->appendUint16(100)
            ->appendBits([$requeue])
            ->appendUint8(206)
        ;

        return $this->connection->write($buffer);
    }

    /**
     * Returns message if there is any waiting in the queue.
     *
     * @param string $queue
     * @param bool   $noAck
     *
     * @return Promise<Message|null>
     */
    public function get(string $queue = '', bool $noAck = false): Promise
    {
        static $getting = false;

        return call(function () use ($queue, $noAck, &$getting) {
            if ($getting) {
                throw Exception\ChannelException::getInProgress();
            }

            $getting = true;

            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(8 + \strlen($queue))
                ->appendUint16(60)
                ->appendUint16(70)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendBits([$noAck])
                ->appendUint8(206)
            ;

            yield $this->connection->write($buffer);

            $promises = [
                $this->await(Protocol\BasicGetOkFrame::class),
                $this->await(Protocol\BasicGetEmptyFrame::class)
            ];

            $frame = yield Promise\first($promises);

            if ($frame instanceof Protocol\BasicGetEmptyFrame) {
                return null;
            }

            /** @var Protocol\ContentHeaderFrame $header */
            $header = yield $this->await(Protocol\ContentHeaderFrame::class);

            $buffer    = new Buffer;
            $remaining = $header->bodySize;

            while ($remaining > 0) {
                /** @var Protocol\ContentBodyFrame $body */
                $body = yield $this->await(Protocol\ContentBodyFrame::class);

                $buffer->append($body->payload);
                $remaining -= $body->size;

                if ($remaining < 0) {
                    $this->state = self::STATE_ERROR;

                    $error = "Body overflow, received " . (-$remaining) . " more bytes.";

                    yield $this->connection->disconnect(Constants::STATUS_SYNTAX_ERROR, $error);

                    throw new Exception\ChannelException($error);
                }
            }

            $getting = false;

            return new Message(
                (string) $buffer,
                $frame->exchange,
                $frame->routingKey,
                null,
                $frame->deliveryTag,
                $frame->redelivered,
                $header->toArray()
            );
        });
    }

    /**
     * Published message to given exchange.
     *
     * @param string $body
     * @param string $exchange
     * @param string $routingKey
     * @param array  $headers
     * @param bool   $mandatory
     * @param bool   $immediate
     *
     * @return Promise<bool>
     */
    public function publish
    (
        string $body,
        string $exchange = '',
        string $routingKey = '',
        array $headers = [],
        bool $mandatory = false,
        bool $immediate = false
    ): Promise
    {
        return call(function () use ($body, $exchange, $routingKey, $headers, $mandatory, $immediate) {
            yield $this->doPublish($body, $exchange, $routingKey, $headers, $mandatory, $immediate);

            return ++$this->deliveryTag;
        });
    }

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     *
     * @return Promise<Protocol\TxSelectOkFrame>
     */
    public function txSelect(): Promise
    {
        if ($this->mode !== self::MODE_REGULAR) {
            throw new Exception\ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        return call(function () {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(4)
                ->appendUint16(90)
                ->appendUint16(10)
                ->appendUint8(206)
            ;

            yield $this->connection->write($buffer);

            $frame = yield $this->await(Protocol\TxSelectOkFrame::class);

            $this->mode = self::MODE_TRANSACTIONAL;

            return $frame;
        });
    }

    /**
     * Commit transaction.
     *
     * @return Promise<Protocol\TxCommitOkFrame>
     */
    public function txCommit(): Promise
    {
        if ($this->mode !== self::MODE_TRANSACTIONAL) {
            throw new Exception\ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }

        return call(function () {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(4)
                ->appendUint16(90)
                ->appendUint16(20)
                ->appendUint8(206);

            yield $this->connection->write($buffer);

            return $this->await(Protocol\TxCommitOkFrame::class);
        });
    }

    /**
     * Rollback transaction.
     *
     * @return Promise<Protocol\TxRollbackOkFrame>
     */
    public function txRollback(): Promise
    {
        if ($this->mode !== self::MODE_TRANSACTIONAL) {
            throw new Exception\ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }

        return call(function () {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(4)
                ->appendUint16(90)
                ->appendUint16(30)
                ->appendUint8(206);

            yield $this->connection->write($buffer);

            return $this->await(Protocol\TxRollbackOkFrame::class);
        });
    }

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     *
     * @param bool     $nowait
     *
     * @return Promise<Protocol\ConfirmSelectOkFrame>
     */
    public function confirmSelect(bool $nowait = false)
    {
        if ($this->mode !== self::MODE_REGULAR) {
            throw new Exception\ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        return call(function () use ($nowait) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(5)
                ->appendUint16(85)
                ->appendUint16(10)
                ->appendBits([$nowait])
                ->appendUint8(206)
            ;

            $result = yield $this->connection->write($buffer);

            if ($nowait === false) {
                $result = yield $this->await(Protocol\ConfirmSelectOkFrame::class);
            }

            $this->mode = self::MODE_CONFIRM;
            $this->deliveryTag = 0;

            return $result;
        });
    }

    /**
     * @param string $queue
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $exclusive
     * @param bool   $autoDelete
     * @param bool   $nowait
     * @param array  $arguments
     *
     * @return Promise
     */
    public function queueDeclare
    (
        string $queue = '',
        bool $passive = false,
        bool $durable = false,
        bool $exclusive = false,
        bool $autoDelete = false,
        bool $nowait = false,
        array $arguments = []
    ): Promise
    {
        $flags = [$passive, $durable, $exclusive, $autoDelete, $nowait];

        return call(function () use ($queue, $flags, $nowait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(50)
                ->appendUint16(10)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendBits($flags)
                ->appendTable($arguments)
            ;

            $frame = $this->methodFrame(50, 10, $buffer);

            if ($nowait) {
                return $this->connection->send($frame);
            } else {
                yield $this->connection->send($frame);

                return $this->await(Protocol\QueueDeclareOkFrame::class);
            }
        });
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param bool   $nowait
     * @param array  $arguments
     *
     * @return Promise
     */
    public function queueBind
    (
        string $queue = '',
        string $exchange = '',
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = []
    ): Promise
    {
        return call(function () use ($queue, $exchange, $routingKey, $nowait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(50)
                ->appendUint16(20)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendString($exchange)
                ->appendString($routingKey)
                ->appendBits([$nowait])
                ->appendTable($arguments)
            ;

            $frame = $this->methodFrame(50, 20, $buffer);

            if ($nowait) {
                return $this->connection->send($frame);
            } else {
                yield $this->connection->send($frame);

                return $this->await(Protocol\QueueBindOkFrame::class);
            }
        });
    }

    /**
     * @param string $queue
     * @param string $exchange
     * @param string $routingKey
     * @param array  $arguments
     *
     * @return Promise
     */
    public function queueUnbind
    (
        string $queue = '',
        string $exchange = '',
        string $routingKey = '',
        array $arguments = []
    ): Promise
    {
        return call(function () use ($queue, $exchange, $routingKey, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(50)
                ->appendUint16(50)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendString($exchange)
                ->appendString($routingKey)
                ->appendTable($arguments)
            ;

            yield $this->connection->send($this->methodFrame(50, 50, $buffer));

            return $this->await(Protocol\QueueUnbindOkFrame::class);
        });
    }

    /**
     * @param string $queue
     * @param bool   $nowait
     *
     * @return Promise<bool>
     */
    public function queuePurge(string $queue = '', bool $nowait = false): Promise
    {
        return call(function () use ($queue, $nowait) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(8 + \strlen($queue))
                ->appendUint16(50)
                ->appendUint16(30)
                ->appendInt16(0)
                ->appendString($queue)
                ->appendBits([$nowait])
                ->appendUint8(206)
            ;

            if ($nowait) {
                return $this->connection->write($buffer);
            } else {
                yield $this->connection->write($buffer);

                return $this->await(Protocol\QueuePurgeOkFrame::class);
            }
        });
    }

    /**
     * @param string $queue
     * @param bool   $ifUnused
     * @param bool   $ifEmpty
     * @param bool   $nowait
     *
     * @return Promise<bool>
     */
    public function queueDelete
    (
        string $queue = '',
        bool $ifUnused = false,
        bool $ifEmpty = false,
        bool $nowait = false
    ): Promise
    {
        $flags = [$ifUnused, $ifEmpty, $nowait];

        return call(function () use ($queue, $flags, $nowait) {
            $buffer = new Buffer;
            $buffer->appendUint8(1);
            $buffer->appendUint16($this->id);
            $buffer->appendUint32(8 + strlen($queue));
            $buffer->appendUint16(50);
            $buffer->appendUint16(40);
            $buffer->appendInt16(0);
            $buffer->appendString($queue);
            $buffer->appendBits($flags);
            $buffer->appendUint8(206);

            if ($nowait) {
                return $this->connection->write($buffer);
            } else {
                yield $this->connection->write($buffer);

                return $this->await(Protocol\QueueDeleteOkFrame::class);
            }
        });
    }

    /**
     * @param string $exchange
     * @param string $exchangeType
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $autoDelete
     * @param bool   $internal
     * @param bool   $nowait
     * @param array  $arguments
     *
     * @return Promise<Protocol\ExchangeDeclareOkFrame>
     */
    public function exchangeDeclare
    (
        string $exchange,
        string $exchangeType = 'direct',
        bool $passive = false,
        bool $durable = false,
        bool $autoDelete = false,
        bool $internal = false,
        bool $nowait = false,
        array $arguments = []
    ): Promise
    {
        $flags = [$passive, $durable, $autoDelete, $internal, $nowait];

        return call(function () use ($exchange, $exchangeType, $flags, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(40)
                ->appendUint16(10)
                ->appendInt16(0)
                ->appendString($exchange)
                ->appendString($exchangeType)
                ->appendBits($flags)
                ->appendTable($arguments)
            ;

            yield $this->connection->send($this->methodFrame(40, 10, $buffer));

            return $this->await(Protocol\ExchangeDeclareOkFrame::class);
        });
    }

    /**
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool   $nowait
     * @param array  $arguments
     *
     * @return Promise<bool|Protocol\ExchangeBindOkFrame>
     */
    public function exchangeBind
    (
        string $destination,
        string $source,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = []
    ): Promise
    {
        return call(function () use ($destination, $source, $routingKey, $nowait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(40)
                ->appendUint16(30)
                ->appendInt16(0)
                ->appendString($destination)
                ->appendString($source)
                ->appendString($routingKey)
                ->appendBits([$nowait])
                ->appendTable($arguments)
            ;

            $frame = $this->methodFrame(40, 30, $buffer);

            if ($nowait) {
                return $this->connection->send($frame);
            } else {
                yield $this->connection->send($frame);

                return $this->await(Protocol\ExchangeBindOkFrame::class);
            }
        });
    }

    /**
     * @param string $destination
     * @param string $source
     * @param string $routingKey
     * @param bool   $nowait
     * @param array  $arguments
     *
     * @return Promise<bool|Protocol\ExchangeUnbindOkFrame>
     */
    public function exchangeUnbind
    (
        string $destination,
        string $source,
        string $routingKey = '',
        bool $nowait = false,
        array $arguments = []
    ): Promise
    {
        return call(function () use ($destination, $source, $routingKey, $nowait, $arguments) {
            $buffer = new Buffer;
            $buffer
                ->appendUint16(40)
                ->appendUint16(40)
                ->appendInt16(0)
                ->appendString($destination)
                ->appendString($source)
                ->appendString($routingKey)
                ->appendBits([$nowait])
                ->appendTable($arguments)
            ;

            $frame = $this->methodFrame(40, 40, $buffer);

            if ($nowait) {
                return $this->connection->send($frame);
            } else {
                yield $this->connection->send($frame);

                return $this->await(Protocol\ExchangeUnbindOkFrame::class);
            }
        });
    }

    /**
     * @param string $exchange
     * @param bool   $unused
     * @param bool   $nowait
     *
     * @return Promise<Protocol\ExchangeDeleteOkFrame>
     */
    public function exchangeDelete(string $exchange, bool $unused = false, bool $nowait = false): Promise
    {
        return call(function () use ($exchange, $unused, $nowait) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16($this->id)
                ->appendUint32(8 + \strlen($exchange))
                ->appendUint16(40)
                ->appendUint16(20)
                ->appendInt16(0)
                ->appendString($exchange)
                ->appendBits([$unused, $nowait])
                ->appendUint8(206)
            ;

            yield $this->connection->write($buffer);

            return $this->await(Protocol\ExchangeDeleteOkFrame::class);
        });
    }

    /**
     * @param string $body
     * @param string $exchange
     * @param string $routingKey
     * @param array  $headers
     * @param bool   $mandatory
     * @param bool   $immediate
     *
     * @return Promise
     */
    public function doPublish
    (
        string $body,
        string $exchange = '',
        string $routingKey = '',
        array $headers = [],
        bool $mandatory = false,
        bool $immediate = false
    ): Promise
    {
        $buffer = new Buffer;

        $flags = 0;
        $contentType = '';
        $contentEncoding = '';
        $type = '';
        $replyTo = '';
        $expiration = '';
        $messageId = '';
        $correlationId = '';
        $userId = '';
        $appId = '';
        $clusterId = '';

        $deliveryMode = null;
        $priority = null;
        $timestamp = null;

        $headersBuffer = null;

        $buffer
            ->appendUint8(1)
            ->appendUint16($this->id)
            ->appendUint32(9 + \strlen($exchange) + \strlen($routingKey))
            ->appendUint16(60)
            ->appendUint16(40)
            ->appendInt16(0)
            ->appendString($exchange)
            ->appendString($routingKey)
            ->appendBits([$mandatory, $immediate])
            ->appendUint8(206)
        ;

        $s = 14;

        if (isset($headers['content-type'])) {
            $flags |= 32768;
            $contentType = $headers['content-type'];
            $s += 1 + \strlen($contentType);
            unset($headers['content-type']);
        }

        if (isset($headers['content-encoding'])) {
            $flags |= 16384;
            $contentEncoding = $headers['content-encoding'];
            $s += 1 + \strlen($contentEncoding);
            unset($headers['content-encoding']);
        }

        if (isset($headers['delivery-mode'])) {
            $flags |= 4096;
            $deliveryMode = (int) $headers['delivery-mode'];
            $s += 1;
            unset($headers['delivery-mode']);
        }

        if (isset($headers['priority'])) {
            $flags |= 2048;
            $priority = (int) $headers['priority'];
            $s += 1;
            unset($headers['priority']);
        }

        if (isset($headers['correlation-id'])) {
            $flags |= 1024;
            $correlationId = $headers['correlation-id'];
            $s += 1 + \strlen($correlationId);
            unset($headers['correlation-id']);
        }

        if (isset($headers['reply-to'])) {
            $flags |= 512;
            $replyTo = $headers['reply-to'];
            $s += 1 + \strlen($replyTo);
            unset($headers['reply-to']);
        }

        if (isset($headers['expiration'])) {
            $flags |= 256;
            $expiration = $headers['expiration'];
            $s += 1 + \strlen($expiration);
            unset($headers['expiration']);
        }

        if (isset($headers['message-id'])) {
            $flags |= 128;
            $messageId = $headers['message-id'];
            $s += 1 + \strlen($messageId);
            unset($headers['message-id']);
        }

        if (isset($headers['timestamp'])) {
            $flags |= 64;
            $timestamp = $headers['timestamp'];
            $s += 8;
            unset($headers['timestamp']);
        }

        if (isset($headers['type'])) {
            $flags |= 32;
            $type = $headers['type'];
            $s += 1 + \strlen($type);
            unset($headers['type']);
        }

        if (isset($headers['user-id'])) {
            $flags |= 16;
            $userId = $headers['user-id'];
            $s += 1 + \strlen($userId);
            unset($headers['user-id']);
        }

        if (isset($headers['app-id'])) {
            $flags |= 8;
            $appId = $headers['app-id'];
            $s += 1 + \strlen($appId);
            unset($headers['app-id']);
        }

        if (isset($headers['cluster-id'])) {
            $flags |= 4;
            $clusterId = $headers['cluster-id'];
            $s += 1 + \strlen($clusterId);
            unset($headers['cluster-id']);
        }

        if (!empty($headers)) {
            $flags |= 8192;
            $headersBuffer = new Buffer;
            $headersBuffer->appendTable($headers);
            $s += $headersBuffer->size();
        }

        $buffer
            ->appendUint8(2)
            ->appendUint16($this->id)
            ->appendUint32($s)
            ->appendUint16(60)
            ->appendUint16(0)
        ;

        $buffer->appendUint64(\strlen($body));

        $buffer->appendUint16($flags);

        if ($flags & 32768) {
            $buffer->appendString($contentType);
        }
        if ($flags & 16384) {
            $buffer->appendString($contentEncoding);
        }
        if ($flags & 8192) {
            $buffer->append($headersBuffer);
        }
        if ($flags & 4096) {
            $buffer->appendUint8($deliveryMode);
        }
        if ($flags & 2048) {
            $buffer->appendUint8($priority);
        }
        if ($flags & 1024) {
            $buffer->appendString($correlationId);
        }
        if ($flags & 512) {
            $buffer->appendString($replyTo);
        }
        if ($flags & 256) {
            $buffer->appendString($expiration);
        }
        if ($flags & 128) {
            $buffer->appendString($messageId);
        }
        if ($flags & 64) {
            $buffer->appendTimestamp($timestamp);
        }
        if ($flags & 32) {
            $buffer->appendString($type);
        }
        if ($flags & 16) {
            $buffer->appendString($userId);
        }
        if ($flags & 8) {
            $buffer->appendString($appId);
        }
        if ($flags & 4) {
            $buffer->appendString($clusterId);
        }

        $buffer->appendUint8(206);

        for ($payloadMax = $this->frameMax - 8, $i = 0, $l = \strlen($body); $i < $l; $i += $payloadMax) {
            $payloadSize = $l - $i;

            if ($payloadSize > $payloadMax) {
                $payloadSize = $payloadMax;
            }

            $buffer
                ->appendUint8(3)
                ->appendUint16($this->id)
                ->appendUint32($payloadSize)
                ->append(\substr($body, $i, $payloadSize))
                ->appendUint8(206)
            ;
        }

        return $this->connection->write($buffer);
    }

    /**
     * @return void
     */
    private function startConsuming(): void
    {
        if ($this->consume) {
            return;
        }

        asyncCall(function () {
            while ($this->state === self::STATE_OPEN) {
                /** @var Protocol\BasicDeliverFrame $deliver */
                $deliver = yield $this->await(Protocol\BasicDeliverFrame::class);

                if (!isset($this->callbacks[$deliver->consumerTag])) {
                    continue;
                }

                /** @var Protocol\ContentHeaderFrame $header */
                /** @var Protocol\ContentBodyFrame $body */
                $header = yield $this->await(Protocol\ContentHeaderFrame::class);

                $buffer    = new Buffer;
                $remaining = $header->bodySize;

                while ($remaining > 0) {
                    /** @var Protocol\ContentBodyFrame $body */
                    $body = yield $this->await(Protocol\ContentBodyFrame::class);

                    $buffer->append($body->payload);
                    $remaining -= $body->size;

                    if ($remaining < 0) {
                        $this->state = self::STATE_ERROR;

                        $error = "Body overflow, received " . (-$remaining) . " more bytes.";

                        yield $this->connection->disconnect(Constants::STATUS_SYNTAX_ERROR, $error);

                        throw new Exception\ChannelException($error);
                    }
                }

                asyncCall($this->callbacks[$deliver->consumerTag], new Message(
                    (string) $buffer,
                    $deliver->exchange,
                    $deliver->routingKey,
                    $deliver->consumerTag,
                    $deliver->deliveryTag,
                    $deliver->redelivered,
                    $header->toArray()
                ), $this);
            }
        });

        $this->consume = true;
    }

    /**
     * @param string $class
     *
     * @return Promise<Protocol\AbstractFrame>
     */
    private function await(string $class): Promise
    {
        return $this->connection->await($class, $this->id);
    }

    /**
     * @param int    $classId
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function methodFrame(int $classId, int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        $frame = new Protocol\MethodFrame($classId, $methodId);
        $frame->channel = $this->id;
        $frame->size    = $buffer->size();
        $frame->payload = $buffer;

        return $frame;
    }
}
