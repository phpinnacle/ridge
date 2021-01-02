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

final class MessageReceiver
{
    public const
        STATE_WAIT = 0,
        STATE_HEAD = 1,
        STATE_BODY = 2;

    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var int
     */
    private $state = self::STATE_WAIT;

    /**
     * @var int
     */
    private $remaining = 0;

    /**
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * @var Protocol\BasicDeliverFrame|null
     */
    private $deliver;

    /**
     * @var Protocol\BasicReturnFrame|null
     */
    private $return;

    /**
     * @var Protocol\ContentHeaderFrame|null
     */
    private $header;

    public function __construct(Channel $channel, Connection $connection)
    {
        $this->channel    = $channel;
        $this->connection = $connection;
        $this->buffer     = new Buffer;
    }

    public function start(): void
    {
        $this->onFrame(Protocol\BasicReturnFrame::class, [$this, 'receiveReturn']);
        $this->onFrame(Protocol\BasicDeliverFrame::class, [$this, 'receiveDeliver']);
        $this->onFrame(Protocol\ContentHeaderFrame::class, [$this, 'receiveHeader']);
        $this->onFrame(Protocol\ContentBodyFrame::class, [$this, 'receiveBody']);
    }

    public function stop(): void
    {
        $this->callbacks = [];
    }

    public function onMessage(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    /**
     * @psalm-param class-string<Protocol\AbstractFrame> $frame
     */
    public function onFrame(string $frame, callable $callback): void
    {
        $this->connection->subscribe($this->channel->id(), $frame, $callback);
    }

    public function receiveReturn(Protocol\BasicReturnFrame $frame): void
    {
        if($this->state !== self::STATE_WAIT)
        {
            return;
        }

        $this->return = $frame;
        $this->state  = self::STATE_HEAD;
    }

    public function receiveDeliver(Protocol\BasicDeliverFrame $frame): void
    {
        if($this->state !== self::STATE_WAIT)
        {
            return;
        }

        $this->deliver = $frame;
        $this->state   = self::STATE_HEAD;
    }

    public function receiveHeader(Protocol\ContentHeaderFrame $frame): void
    {
        if($this->state !== self::STATE_HEAD)
        {
            return;
        }

        $this->state     = self::STATE_BODY;
        $this->header    = $frame;
        $this->remaining = $frame->bodySize;

        $this->runCallbacks();
    }

    public function receiveBody(Protocol\ContentBodyFrame $frame): void
    {
        if($this->state !== self::STATE_BODY)
        {
            return;
        }

        $this->buffer->append((string) $frame->payload);

        $this->remaining -= (int) $frame->size;

        if($this->remaining < 0)
        {
            throw Exception\ChannelException::bodyOverflow($this->remaining);
        }

        $this->runCallbacks();
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\ChannelException
     */
    private function runCallbacks(): void
    {
        if($this->remaining !== 0)
        {
            return;
        }

        if($this->return)
        {
            $message = new Message(
                $this->buffer->flush(),
                $this->return->exchange,
                $this->return->routingKey,
                null,
                null,
                false,
                true,
                $this->header !== null ? $this->header->toArray() : []
            );
        }
        else if($this->deliver)
        {
            $message = new Message(
                $this->buffer->flush(),
                $this->deliver->exchange,
                $this->deliver->routingKey,
                $this->deliver->consumerTag,
                $this->deliver->deliveryTag,
                $this->deliver->redelivered,
                false,
                $this->header !== null ? $this->header->toArray() : []
            );
        }
        else
        {
            throw Exception\ChannelException::frameOrder();
        }

        $this->return  = null;
        $this->deliver = null;
        $this->header  = null;

        foreach($this->callbacks as $callback)
        {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            asyncCall($callback, $message);
        }

        $this->state = self::STATE_WAIT;
    }
}
