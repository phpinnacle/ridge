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
     * @var MessageReceiver
     */
    private $receiver;

    /**
     * @var callable[]
     * @psalm-var array<string, callable>
     */
    private $listeners = [];

    public function __construct(Channel $channel, MessageReceiver $receiver)
    {
        $this->channel  = $channel;
        $this->receiver = $receiver;
    }

    public function start(): void
    {
        $this->receiver->onMessage(
            function(Message $message)
            {
                if(!$tag = $message->consumerTag())
                {
                    return;
                }

                if(!isset($this->listeners[$tag]))
                {
                    return;
                }

                /** @psalm-suppress MixedArgumentTypeCoercion */
                asyncCall($this->listeners[$tag], $message, $this->channel);
            }
        );
    }

    public function stop(): void
    {
        $this->listeners = [];
    }

    public function subscribe(string $tag, callable $listener): void
    {
        $this->listeners[$tag] = $listener;
    }

    public function cancel(string $tag): void
    {
        unset($this->listeners[$tag]);
    }
}
