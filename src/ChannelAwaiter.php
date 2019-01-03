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

use function Amp\call;
use Amp\Deferred;
use Amp\Promise;

final class ChannelAwaiter
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var callable[]
     */
    private $callbacks;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param Protocol\AbstractFrame $frame
     *
     * @return Promise
     */
    public function receive(Protocol\AbstractFrame $frame): Promise
    {
        return call(function () use ($frame) {
            foreach ($this->callbacks as $k => $callback) {
                $result = yield call($callback, $frame);

                if ($result === true) {
                    unset($this->callbacks[$k]);

                    return true;
                }

                unset($result);
            }

            return false;
        });
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ContentHeaderFrame>
     */
    public function awaitContentHeader(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ContentHeaderFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ContentBodyFrame>
     */
    public function awaitContentBody(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ContentBodyFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\QueueDeclareOkFrame>
     */
    public function awaitQueueDeclareOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\QueueDeclareOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\QueueBindOkFrame>
     */
    public function awaitQueueBindOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\QueueBindOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\QueueUnbindOkFrame>
     */
    public function awaitQueueUnbindOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\QueueUnbindOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\QueueDeleteOkFrame>
     */
    public function awaitQueueDeleteOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\QueueDeleteOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\BasicConsumeOkFrame>
     */
    public function awaitConsumeOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\BasicConsumeOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\BasicCancelOkFrame>
     */
    public function awaitCancelOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\BasicCancelOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\BasicDeliverFrame>
     */
    public function awaitDeliver(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\BasicDeliverFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\BasicAckFrame>
     */
    public function awaitAck(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\BasicAckFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\BasicNackFrame>
     */
    public function awaitNack(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\BasicNackFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ExchangeDeclareOkFrame>
     */
    public function awaitExchangeDeclareOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ExchangeDeclareOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ExchangeBindOkFrame>
     */
    public function awaitExchangeBindOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ExchangeBindOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ExchangeUnbindOkFrame>
     */
    public function awaitExchangeUnbindOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ExchangeUnbindOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ExchangeDeleteOkFrame>
     */
    public function awaitExchangeDeleteOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ExchangeDeleteOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ChannelOpenOkFrame>
     */
    public function awaitChannelOpenOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ChannelOpenOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ChannelCloseFrame>
     */
    public function awaitChannelClose(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ChannelCloseFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\ChannelCloseOkFrame>
     */
    public function awaitChannelCloseOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\ChannelCloseOkFrame::class);
    }

    /**
     * @param int $channel
     *
     * @return Promise<Protocol\BasicQosOkFrame>
     */
    public function awaitQosOk(int $channel): Promise
    {
        return $this->awaitFrame($channel, Protocol\BasicQosOkFrame::class);
    }

    /**
     * @param int    $channel
     * @param string $class
     *
     * @return Promise<Protocol\AbstractFrame>
     */
    private function awaitFrame(int $channel, string $class): Promise
    {
        $deferred = new Deferred();

        $this->callbacks[] = function (Protocol\AbstractFrame $frame) use ($channel, $class, $deferred) {
            if ($frame->channel === $channel && \is_a($frame, $class)) {
                $deferred->resolve($frame);

                return true;
            }

            if ($frame instanceof Protocol\ChannelCloseFrame && $frame->channel === $channel) {
                yield $this->client->channelCloseOk($channel);

                $deferred->fail(new Exception\ClientException($frame->replyText, $frame->replyCode));

                return true;
            }

            if ($frame instanceof Protocol\ConnectionCloseFrame) {
                yield $this->client->connectionCloseOk();

                $deferred->fail(new Exception\ClientException($frame->replyText, $frame->replyCode));

                return true;
            }

            return false;
        };

        return $deferred->promise();
    }
}
