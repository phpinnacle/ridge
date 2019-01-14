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

final class Consumer
{
    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var callable[]
     */
    private $callbacks = [];

    /**
     * @var Protocol\BasicDeliverFrame
     */
    private $deliver;

    /**
     * @var Protocol\ContentHeaderFrame
     */
    private $header;

    /**
     * @var int
     */
    private $remaining = 0;

    /**
     * @param Channel $channel
     */
    public function __construct(Channel $channel)
    {
        $this->channel = $channel;
        $this->buffer  = new Buffer;
    }

    /**
     * @param string   $consumerTag
     * @param callable $callback
     */
    public function listen(string $consumerTag, callable $callback): void
    {
        $this->callbacks[$consumerTag] = $callback;
    }

    /**
     * @param string $consumerTag
     */
    public function cancel(string $consumerTag): void
    {
        unset($this->callbacks[$consumerTag]);
    }

    /**
     * @param Protocol\BasicDeliverFrame $frame
     *
     * @return void
     */
    public function onDeliver(Protocol\BasicDeliverFrame $frame): void
    {
        if (!isset($this->callbacks[$frame->consumerTag])) {
            return;
        }

        $this->deliver = $frame;
    }

    /**
     * @param Protocol\ContentHeaderFrame $frame
     *
     * @return void
     */
    public function onHeader(Protocol\ContentHeaderFrame $frame): void
    {
        if ($this->deliver === null) {
            return;
        }

        $this->header    = $frame;
        $this->remaining = $frame->bodySize;

        $this->runCallbacks();
    }

    /**
     * @param Protocol\ContentBodyFrame $frame
     *
     * @return void
     */
    public function onBody(Protocol\ContentBodyFrame $frame): void
    {
        if ($this->header === null) {
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

        $consumer = $this->deliver->consumerTag;
        $message  = new Message(
            $this->buffer->flush(),
            $this->deliver->exchange,
            $this->deliver->routingKey,
            $this->deliver->consumerTag,
            $this->deliver->deliveryTag,
            $this->deliver->redelivered,
            $this->header->toArray()
        );

        $this->deliver = null;
        $this->header = null;

        asyncCall($this->callbacks[$consumer], $message, $this->channel);
    }
}
