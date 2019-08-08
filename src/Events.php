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

final class Events
{
    /**
     * @var Channel
     */
    private $channel;

    /**
     * @var MessageReceiver
     */
    private $receiver;

    /**
     * @param Channel         $channel
     * @param MessageReceiver $receiver
     */
    public function __construct(Channel $channel, MessageReceiver $receiver)
    {
        $this->channel  = $channel;
        $this->receiver = $receiver;
    }

    /**
     * @param callable $listener
     *
     * @return self
     */
    public function onAck(callable $listener): self
    {
        $this->onFrame(Protocol\BasicAckFrame::class, $listener);

        return $this;
    }

    /**
     * @param callable $listener
     *
     * @return self
     */
    public function onNack(callable $listener): self
    {
        $this->onFrame(Protocol\BasicNackFrame::class, $listener);

        return $this;
    }

    /**
     * @param callable $listener
     *
     * @return self
     */
    public function onReturn(callable $listener): self
    {
        $this->receiver->onMessage(function (Message $message) use ($listener) {
            if (!$message->returned()) {
                return;
            }

            asyncCall($listener, $message, $this->channel);
        });

        return $this;
    }

    /**
     * @param string   $frame
     * @param callable $callback
     *
     * @return void
     */
    private function onFrame(string $frame, callable $callback): void
    {
        $this->receiver->onFrame($frame, function (Protocol\AcknowledgmentFrame $frame) use ($callback) {
            asyncCall($callback, $frame->deliveryTag, $frame->multiple, $this->channel);
        });
    }
}
