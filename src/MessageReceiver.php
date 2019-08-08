<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PHPinnacle\Ridge;

use function Amp\asyncCall;

final class MessageReceiver
{
    const
        STATE_WAIT = 0,
        STATE_HEAD = 1,
        STATE_BODY = 2
    ;

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
     * @var Protocol\BasicDeliverFrame
     */
    private $deliver;

    /**
     * @var Protocol\BasicReturnFrame
     */
    private $return;

    /**
     * @var Protocol\ContentHeaderFrame
     */
    private $header;

    /**
     * @param Channel    $channel
     * @param Connection $connection
     */
    public function __construct(Channel $channel, Connection $connection)
    {
        $this->channel    = $channel;
        $this->connection = $connection;
        $this->buffer     = new Buffer;
    }

    /**
     * @return void
     */
    public function start(): void
    {
        $this->onFrame(Protocol\BasicReturnFrame::class, [$this, 'receiveReturn']);
        $this->onFrame(Protocol\BasicDeliverFrame::class, [$this, 'receiveDeliver']);
        $this->onFrame(Protocol\ContentHeaderFrame::class, [$this, 'receiveHeader']);
        $this->onFrame(Protocol\ContentBodyFrame::class, [$this, 'receiveBody']);
    }

    /**
     * @return void
     */
    public function stop(): void
    {
        $this->callbacks = [];
    }

    /**
     * @param callable $callback
     *
     * @return void
     */
    public function onMessage(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    /**
     * @param string   $frame
     * @param callable $callback
     *
     * @return void
     */
    public function onFrame(string $frame, callable $callback): void
    {
        $this->connection->subscribe($this->channel->id(), $frame, $callback);
    }

    /**
     * @param Protocol\BasicReturnFrame $frame
     *
     * @return void
     */
    public function receiveReturn(Protocol\BasicReturnFrame $frame): void
    {
        if ($this->state !== self::STATE_WAIT) {
            return;
        }

        $this->return = $frame;
        $this->state  = self::STATE_HEAD;
    }

    /**
     * @param Protocol\BasicDeliverFrame $frame
     *
     * @return void
     */
    public function receiveDeliver(Protocol\BasicDeliverFrame $frame): void
    {
        if ($this->state !== self::STATE_WAIT) {
            return;
        }

        $this->deliver = $frame;
        $this->state   = self::STATE_HEAD;
    }

    /**
     * @param Protocol\ContentHeaderFrame $frame
     *
     * @return void
     */
    public function receiveHeader(Protocol\ContentHeaderFrame $frame): void
    {
        if ($this->state !== self::STATE_HEAD) {
            return;
        }

        $this->state     = self::STATE_BODY;
        $this->header    = $frame;
        $this->remaining = $frame->bodySize;

        $this->runCallbacks();
    }

    /**
     * @param Protocol\ContentBodyFrame $frame
     *
     * @return void
     */
    public function receiveBody(Protocol\ContentBodyFrame $frame): void
    {
        if ($this->state !== self::STATE_BODY) {
            return;
        }

        $this->buffer->append($frame->payload);

        $this->remaining -= $frame->size;

        if ($this->remaining < 0) {
            throw Exception\ChannelException::bodyOverflow($this->remaining);
        }

        $this->runCallbacks();
    }

    /**
     * @return void
     */
    private function runCallbacks(): void
    {
        if ($this->remaining !== 0) {
            return;
        }

        if ($this->return) {
            $message = new Message(
                $this->buffer->flush(),
                $this->return->exchange,
                $this->return->routingKey,
                null,
                null,
                false,
                true,
                $this->header->toArray()
            );
        } elseif ($this->deliver) {
            $message = new Message(
                $this->buffer->flush(),
                $this->deliver->exchange,
                $this->deliver->routingKey,
                $this->deliver->consumerTag,
                $this->deliver->deliveryTag,
                $this->deliver->redelivered,
                false,
                $this->header->toArray()
            );
        } else {
            throw Exception\ChannelException::frameOrder();
        }

        $this->return  = null;
        $this->deliver = null;
        $this->header  = null;

        foreach ($this->callbacks as $callback) {
            asyncCall($callback, $message);
        }

        $this->state = self::STATE_WAIT;
    }
}
