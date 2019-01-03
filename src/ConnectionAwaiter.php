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

final class ConnectionAwaiter
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var callable[]
     */
    private $callbacks = [];

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
     * @return Promise<Protocol\ConnectionStartFrame>
     */
    public function awaitConnectionStart(): Promise
    {
        return $this->awaitFrame(Protocol\ConnectionStartFrame::class);
    }

    /**
     * @return Promise<Protocol\ConnectionTuneFrame>
     */
    public function awaitConnectionTune(): Promise
    {
        return $this->awaitFrame(Protocol\ConnectionTuneFrame::class);
    }

    /**
     * @return Promise<Protocol\ConnectionOpenOkFrame>
     */
    public function awaitConnectionOpenOk(): Promise
    {
        return $this->awaitFrame(Protocol\ConnectionOpenOkFrame::class);
    }

    /**
     * @return Promise<Protocol\ConnectionCloseFrame>
     */
    public function awaitConnectionClose(): Promise
    {
        return $this->awaitFrame(Protocol\ConnectionCloseFrame::class);
    }

    /**
     * @return Promise<Protocol\ConnectionCloseOkFrame>
     */
    public function awaitConnectionCloseOk(): Promise
    {
        return $this->awaitFrame(Protocol\ConnectionCloseOkFrame::class);
    }

    /**
     * @return Promise<Protocol\ConnectionBlockedFrame>
     */
    public function awaitConnectionBlocked(): Promise
    {
        return $this->awaitFrame(Protocol\ConnectionBlockedFrame::class);
    }

    /**
     * @return Promise<Protocol\ConnectionUnblockedFrame>
     */
    public function awaitConnectionUnblocked(): Promise
    {
        return $this->awaitFrame(Protocol\ConnectionUnblockedFrame::class);
    }

    /**
     * @param string $class
     *
     * @return Promise<Protocol\AbstractFrame>
     */
    private function awaitFrame(string $class): Promise
    {
        $deferred = new Deferred();

        $this->callbacks[] = function (Protocol\AbstractFrame $frame) use ($class, $deferred) {
            if (\is_a($frame, $class)) {
                $deferred->resolve($frame);

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
