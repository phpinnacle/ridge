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
use Amp\Promise;

final class Client
{
    private const
        STATE_NOT_CONNECTED = 0,
        STATE_CONNECTING    = 1,
        STATE_CONNECTED     = 2,
        STATE_DISCONNECTING = 3
    ;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var int
     */
    private $state = self::STATE_NOT_CONNECTED;

    /**
     * @var Channel[]
     */
    private $channels = [];

    /**
     * @var int
     */
    private $nextChannelId = 1;

    /**
     * @var int
     */
    private $channelMax = 0xFFFF;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return Promise<void>
     */
    public function connect(): Promise
    {
        return call(function () {
            if ($this->state !== self::STATE_NOT_CONNECTED) {
                throw new \RuntimeException('Client already connected/connecting');
            }

            $this->state = self::STATE_CONNECTING;

            $this->connection = new Connection($this->config);

            yield $this->connection->connect();

            $this->state = self::STATE_CONNECTED;
        });
    }

    /**
     * @param int    $replyCode
     * @param string $replyText
     *
     * @return Promise<void>
     */
    public function disconnect($replyCode = 0, $replyText = ''): Promise
    {
        return call(function() use ($replyCode, $replyText) {
            if (\in_array($this->state, [self::STATE_NOT_CONNECTED, self::STATE_DISCONNECTING])) {
                return;
            }

            if ($this->state !== self::STATE_CONNECTED) {
                throw new Exception\ClientException('Client is not connected');
            }

            $this->state = self::STATE_DISCONNECTING;

            if ($replyCode === 0) {
                $promises = [];

                foreach($this->channels as $channel) {
                    $promises[] = $channel->close($replyCode, $replyText);
                }

                yield $promises;
            }

            yield $this->connection->disconnect($replyCode, $replyText);

            $this->state = self::STATE_NOT_CONNECTED;
        });
    }

    /**
     * @return Promise<Channel>
     */
    public function channel(): Promise
    {
        return call(function() {
            if ($this->state !== self::STATE_CONNECTED) {
                throw new Exception\ClientException('Client is not connected');
            }

            try {
                $channelId = $this->findChannelId();
                $channel = new Channel($channelId, $this->connection);

                $this->channels[$channelId] = &$channel;

                yield $channel->open();
                yield $channel->qos($this->config->qosSize(), $this->config->qosCount(), $this->config->qosGlobal());

                return $channel;
            } catch(\Throwable $throwable) {
                throw new Exception\ClientException('channel.open unexpected response', $throwable->getCode(), $throwable);
            }
        });
    }

    /**
     * @return int
     */
    private function findChannelId(): int
    {
        // first check in range [next, max] ...
        for (
            $channelId = $this->nextChannelId;
            $channelId <= $this->channelMax;
            ++$channelId
        ) {
            if (!isset($this->channels[$channelId])) {
                $this->nextChannelId = $channelId + 1;

                return $channelId;
            }
        }

        // then check in range [min, next) ...
        for (
            $channelId = 1;
            $channelId < $this->nextChannelId;
            ++$channelId
        ) {
            if (!isset($this->channels[$channelId])) {
                $this->nextChannelId = $channelId + 1;

                return $channelId;
            }
        }

        throw new Exception\ClientException("No available channels");
    }
}
