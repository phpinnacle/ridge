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

final class Channel
{
    const
        STATE_READY     = 1,
        STATE_WAIT_HEAD = 2,
        STATE_WAIT_BODY = 3,
        STATE_ERROR     = 4,
        STATE_CLOSING   = 5,
        STATE_CLOSED    = 6
    ;

    const
        MODE_REGULAR       = 1, // Regular AMQP guarantees of published messages delivery.
        MODE_TRANSACTIONAL = 2, // Messages are published after 'tx.commit'.
        MODE_CONFIRM       = 3  // Broker sends asynchronously 'basic.ack's for delivered messages.
    ;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $state;

    /**
     * @var
     */
    private $mode;

    /**
     * @var int
     */
    private $bodySizeRemaining;

    /**
     * @var Buffer
     */
    private $bodyBuffer;

    /**
     * @var Protocol\BasicReturnFrame
     */
    private $returnFrame;

    /**
     * @var Protocol\BasicDeliverFrame
     */
    private $deliverFrame;

    /**
     * @var Protocol\BasicGetOkFrame
     */
    private $getOkFrame;

    /**
     * @var Protocol\ContentHeaderFrame
     */
    private $headerFrame;

    /**
     * @var callable[]
     */
    private $returnCallbacks = [];

    /**
     * @var callable[]
     */
    private $ackCallbacks = [];

    /**
     * @var callable[]
     */
    private $deliverCallbacks;

    /**
     * @var Deferred
     */
    private $getDeferred;

    /**
     * @var Deferred
     */
    private $closeDeferred;

    /**
     * @var
     */
    private $closePromise;

    /**
     * @var int
     */
    private $deliveryTag = 0;

    /**
     * @param Client $client
     * @param int        $id
     */
    public function __construct(Client $client, int $id)
    {
        $this->client = $client;
        $this->id     = $id;
    }

    /**
     * Listener is called whenever 'basic.return' frame is received with arguments (Message $returnedMessage, MethodBasicReturnFrame $frame)
     *
     * @param callable $callback
     * 
     * @return self
     */
    public function addReturnListener(callable $callback): self
    {
        $this->removeReturnListener($callback); // remove if previously added to prevent calling multiple times
        $this->returnCallbacks[] = $callback;

        return $this;
    }

    /**
     * Removes registered return listener. If the callback is not registered, this is noop.
     *
     * @param callable $callback
     *
     * @return self
     */
    public function removeReturnListener(callable $callback): self
    {
        foreach ($this->returnCallbacks as $k => $v) {
            if ($v === $callback) {
                unset($this->returnCallbacks[$k]);
            }
        }

        return $this;
    }

    /**
     * Listener is called whenever 'basic.ack' or 'basic.nack' is received.
     *
     * @param callable $callback
     * @return self
     */
    public function addAckListener(callable $callback)
    {
        if ($this->mode !== self::MODE_CONFIRM) {
            throw new Exception\ChannelException("Ack/nack listener can be added when channel in confirm mode.");
        }

        $this->removeAckListener($callback);
        $this->ackCallbacks[] = $callback;

        return $this;
    }

    /**
     * Removes registered ack/nack listener. If the callback is not registered, this is noop.
     *
     * @param callable $callback
     * @return self
     */
    public function removeAckListener(callable $callback)
    {
        if ($this->mode !== self::MODE_CONFIRM) {
            throw new Exception\ChannelException("Ack/nack listener can be removed when channel in confirm mode.");
        }

        foreach ($this->ackCallbacks as $k => $v) {
            if ($v === $callback) {
                unset($this->ackCallbacks[$k]);
            }
        }

        return $this;
    }

    /**
     * Closes channel.
     *
     * Always returns a promise, because there can be outstanding messages to be processed.
     *
     * @param int    $replyCode
     * @param string $replyText
     * 
     * @return Promise
     */
    public function close(int $replyCode = 0, string $replyText = ''): Promise
    {
        return call(function () use ($replyCode, $replyText) {
            if ($this->state === self::STATE_CLOSED) {
                throw new Exception\ChannelException("Trying to close already closed channel #{$this->id}.");
            }

            if ($this->state === self::STATE_CLOSING) {
                return $this->closePromise;
            }

            $this->state = self::STATE_CLOSING;

            yield $this->client->channelClose($this->id, $replyCode, $replyText, 0, 0);

            return $this->client->removeChannel($this->id);
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
    public function consume(callable $callback, $queue = '', $consumerTag = '', $noLocal = false, $noAck = false, $exclusive = false, $nowait = false, $arguments = [])
    {
        $flags = [$noLocal, $noAck, $exclusive, $nowait];

        return call(function () use ($callback, $queue, $consumerTag, $flags, $arguments) {
            $frame = yield $this->client->consume($this->id, $queue, $consumerTag, ...$flags);

            if ($frame instanceof Protocol\BasicConsumeOkFrame) {
                $this->deliverCallbacks[$frame->consumerTag] = $callback;
            } else {
                throw new Exception\ChannelException(
                    "basic.consume unexpected response of type " . gettype($frame) .
                    (is_object($frame) ? " (" . get_class($frame) . ")" : "") .
                    "."
                );
            }
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
        return $this->client->ack($this->id, $message->deliveryTag, $multiple);
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
    public function nack(Message $message, $multiple = false, $requeue = true): Promise
    {
        return $this->client->nack($this->id, $message->deliveryTag, $multiple, $requeue);
    }

    /**
     * Rejects given message.
     *
     * @param Message $message
     * @param bool    $requeue
     *
     * @return Promise<boolean>
     */
    public function reject(Message $message, $requeue = true)
    {
        return $this->client->reject($this->id, $message->deliveryTag, $requeue);
    }

    /**
     * Returns message if there is any waiting in the queue.
     *
     * @param string $queue
     * @param bool   $noAck
     *
     * @return Promise<Message>
     */
    public function get($queue = '', $noAck = false)
    {
        if ($this->getDeferred !== null) {
            throw new Exception\ChannelException("Another 'basic.get' already in progress. You should use 'basic.consume' instead of multiple 'basic.get'.");
        }

        $response = $this->getImpl($queue, $noAck);

        if ($response instanceof PromiseInterface) {
            $this->getDeferred = new Deferred();

            $response->done(function ($frame) {
                if ($frame instanceof Protocol\BasicGetEmptyFrame) {
                    // deferred has to be first nullified and then resolved, otherwise results in race condition
                    $deferred = $this->getDeferred;
                    $this->getDeferred = null;
                    $deferred->resolve(null);

                } elseif ($frame instanceof Protocol\BasicGetOkFrame) {
                    $this->getOkFrame = $frame;
                    $this->state = self::STATE_WAIT_HEAD;

                } else {
                    throw new \LogicException("This statement should never be reached.");
                }
            });

            return $this->getDeferred->promise();
        } elseif ($response instanceof Protocol\BasicGetEmptyFrame) {
            return null;
        } elseif ($response instanceof Protocol\BasicGetOkFrame) {
            $this->state = self::STATE_WAIT_HEAD;

            $headerFrame = $this->client->awaitContentHeader($this->id);
            $this->headerFrame = $headerFrame;
            $this->bodySizeRemaining = $headerFrame->bodySize;
            $this->state = self::STATE_WAIT_BODY;

            while ($this->bodySizeRemaining > 0) {
                $bodyFrame = $this->client->awaitContentBody($this->id);

                $this->bodyBuffer->append($bodyFrame->payload);
                $this->bodySizeRemaining -= $bodyFrame->payloadSize;

                if ($this->bodySizeRemaining < 0) {
                    $this->state = self::STATE_ERROR;
                    $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, $errorMessage = "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");
                    throw new Exception\ChannelException($errorMessage);
                }
            }

            $this->state = self::STATE_READY;

            $message = new Message(
                null,
                $response->deliveryTag,
                $response->redelivered,
                $response->exchange,
                $response->routingKey,
                $this->headerFrame->toArray(),
                $this->bodyBuffer->consume($this->bodyBuffer->getLength())
            );

            $this->headerFrame = null;

            return $message;

        } else {
            throw new \LogicException("This statement should never be reached.");
        }
    }

    /**
     * Published message to given exchange.
     *
     * @param string $body
     * @param array  $headers
     * @param string $exchange
     * @param string $routingKey
     * @param bool   $mandatory
     * @param bool   $immediate
     *
     * @return Promise<bool>
     */
    public function publish($body, array $headers = [], $exchange = '', $routingKey = '', $mandatory = false, $immediate = false)
    {
        return call(function () use ($body, $headers, $exchange, $routingKey, $mandatory, $immediate) {
            yield $this->client->publish($this->id, $body, $headers, $exchange, $routingKey, $mandatory, $immediate);

            return ++$this->deliveryTag;
        });
    }

    /**
     * Cancels given consumer subscription.
     *
     * @param string $consumerTag
     * @param bool   $nowait
     *
     * @return Promise<Protocol\BasicCancelOkFrame>
     */
    public function cancel($consumerTag, $nowait = false)
    {
        $response = $this->cancelImpl($consumerTag, $nowait);
        unset($this->deliverCallbacks[$consumerTag]);
        return $response;
    }

    /**
     * Changes channel to transactional mode. All messages are published to queues only after {@link txCommit()} is called.
     *
     * @return Promise<Protocol\TxSelectOkFrame>
     */
    public function txSelect()
    {
        if ($this->mode !== self::MODE_REGULAR) {
            throw new Exception\ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->txSelectImpl();

        if ($response instanceof PromiseInterface) {
            return $response->then(function ($response) {
                $this->mode = self::MODE_TRANSACTIONAL;
                return $response;
            });

        } else {
            $this->mode = self::MODE_TRANSACTIONAL;
            return $response;
        }
    }

    /**
     * Commit transaction.
     *
     * @return Promise<Protocol\TxCommitOkFrame>
     */
    public function txCommit()
    {
        if ($this->mode !== self::MODE_TRANSACTIONAL) {
            throw new Exception\ChannelException("Channel not in transactional mode, cannot call 'tx.commit'.");
        }

        return $this->txCommitImpl();
    }

    /**
     * Rollback transaction.
     *
     * @return Promise<Protocol\TxRollbackOkFrame>
     */
    public function txRollback()
    {
        if ($this->mode !== self::MODE_TRANSACTIONAL) {
            throw new Exception\ChannelException("Channel not in transactional mode, cannot call 'tx.rollback'.");
        }

        return $this->txRollbackImpl();
    }

    /**
     * Changes channel to confirm mode. Broker then asynchronously sends 'basic.ack's for published messages.
     *
     * @param bool $nowait
     * @return Promise<Protocol\ConfirmSelectOkFrame>
     */
    public function confirmSelect(callable $callback = null, $nowait = false)
    {
        if ($this->mode !== self::MODE_REGULAR) {
            throw new Exception\ChannelException("Channel not in regular mode, cannot change to transactional mode.");
        }

        $response = $this->confirmSelectImpl($nowait);

        if ($response instanceof PromiseInterface) {
            return $response->then(function ($response) use ($callback) {
                $this->enterConfirmMode($callback);
                return $response;
            });

        } else {
            $this->enterConfirmMode($callback);
            return $response;
        }
    }

    private function enterConfirmMode(callable $callback = null)
    {
        $this->mode = self::MODE_CONFIRM;
        $this->deliveryTag = 0;

        if ($callback) {
            $this->addAckListener($callback);
        }
    }

    /**
     * Callback after channel-level frame has been received.
     *
     * @param Protocol\AbstractFrame $frame
     */
    public function onFrameReceived(Protocol\AbstractFrame $frame)
    {
        if ($this->state === self::STATE_ERROR) {
            throw new Exception\ChannelException("Channel in error state.");
        }

        if ($this->state === self::STATE_CLOSED) {
            throw new Exception\ChannelException("Received frame #{$frame->type} on closed channel #{$this->id}.");
        }

        if ($frame instanceof Protocol\MethodFrame) {
            if ($this->state === self::STATE_CLOSING && !($frame instanceof Protocol\ChannelCloseOkFrame)) {
                return;
            } elseif ($this->state !== self::STATE_READY && !($frame instanceof Protocol\ChannelCloseOkFrame)) {
                $currentState = $this->state;

                $this->state = self::STATE_ERROR;

                if ($currentState === self::STATE_WAIT_HEAD) {
                    $msg = "Got method frame, expected header frame.";
                } elseif ($currentState === self::STATE_WAIT_BODY) {
                    $msg = "Got method frame, expected body frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new Exception\ChannelException("Unexpected frame: " . $msg);
            }

            if ($frame instanceof Protocol\ChannelCloseOkFrame) {
                $this->state = self::STATE_CLOSED;

                if ($this->closeDeferred !== null) {
                    $this->closeDeferred->resolve($this->id);
                }

                // break reference cycle, must be called after resolving promise
                $this->client = null;
                // break consumers' reference cycle
                $this->deliverCallbacks = [];

            } elseif ($frame instanceof Protocol\BasicReturnFrame) {
                $this->returnFrame = $frame;
                $this->state = self::STATE_WAIT_HEAD;

            } elseif ($frame instanceof Protocol\BasicDeliverFrame) {
                $this->deliverFrame = $frame;
                $this->state = self::STATE_WAIT_HEAD;

            } elseif ($frame instanceof Protocol\BasicAckFrame) {
                foreach ($this->ackCallbacks as $callback) {
                    $callback($frame);
                }

            } elseif ($frame instanceof Protocol\BasicNackFrame) {
                foreach ($this->ackCallbacks as $callback) {
                    $callback($frame);
                }

            } else {
                throw new Exception\ChannelException("Unhandled method frame " . get_class($frame) . ".");
            }

        } elseif ($frame instanceof Protocol\ContentHeaderFrame) {
            if ($this->state === self::STATE_CLOSING) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== self::STATE_WAIT_HEAD) {
                $currentState = $this->state;
                $this->state = self::STATE_ERROR;

                if ($currentState === self::STATE_READY) {
                    $msg = "Got header frame, expected method frame.";
                } elseif ($currentState === self::STATE_WAIT_BODY) {
                    $msg = "Got header frame, expected content frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new Exception\ChannelException("Unexpected frame: " . $msg);
            }

            $this->headerFrame = $frame;
            $this->bodySizeRemaining = $frame->bodySize;

            if ($this->bodySizeRemaining > 0) {
                $this->state = self::STATE_WAIT_BODY;
            } else {
                $this->state = self::STATE_READY;
                $this->onBodyComplete();
            }

        } elseif ($frame instanceof Protocol\ContentBodyFrame) {
            if ($this->state === self::STATE_CLOSING) {
                // drop frames in closing state
                return;

            } elseif ($this->state !== self::STATE_WAIT_BODY) {
                $currentState = $this->state;
                $this->state = self::STATE_ERROR;

                if ($currentState === self::STATE_READY) {
                    $msg = "Got body frame, expected method frame.";
                } elseif ($currentState === self::STATE_WAIT_HEAD) {
                    $msg = "Got body frame, expected header frame.";
                } else {
                    throw new \LogicException("Unhandled channel state.");
                }

                $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, $msg);

                throw new Exception\ChannelException("Unexpected frame: " . $msg);
            }

            $this->bodyBuffer->append($frame->payload);
            $this->bodySizeRemaining -= $frame->size;

            if ($this->bodySizeRemaining < 0) {
                $this->state = self::STATE_ERROR;
                $this->client->disconnect(Constants::STATUS_SYNTAX_ERROR, "Body overflow, received " . (-$this->bodySizeRemaining) . " more bytes.");

            } elseif ($this->bodySizeRemaining === 0) {
                $this->state = self::STATE_READY;
                $this->onBodyComplete();
            }
        } elseif ($frame instanceof Protocol\HeartbeatFrame) {
            $this->client->disconnect(Constants::STATUS_UNEXPECTED_FRAME, "Got heartbeat on non-zero channel.");
            throw new Exception\ChannelException("Unexpected heartbeat frame.");
        } else {
            throw new Exception\ChannelException("Unhandled frame " . get_class($frame) . ".");
        }
    }

    /**
     * Callback after content body has been completely received.
     */
    protected function onBodyComplete()
    {
        if ($this->returnFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());
            $message = new Message(
                null,
                null,
                false,
                $this->returnFrame->exchange,
                $this->returnFrame->routingKey,
                $this->headerFrame->toArray(),
                $content
            );

            foreach ($this->returnCallbacks as $callback) {
                $callback($message, $this->returnFrame);
            }

            $this->returnFrame = null;
            $this->headerFrame = null;

        } elseif ($this->deliverFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());
            if (isset($this->deliverCallbacks[$this->deliverFrame->consumerTag])) {
                $message = new Message(
                    $this->deliverFrame->consumerTag,
                    $this->deliverFrame->deliveryTag,
                    $this->deliverFrame->redelivered,
                    $this->deliverFrame->exchange,
                    $this->deliverFrame->routingKey,
                    $this->headerFrame->toArray(),
                    $content
                );

                $callback = $this->deliverCallbacks[$this->deliverFrame->consumerTag];

                $callback($message, $this, $this->client);
            }

            $this->deliverFrame = null;
            $this->headerFrame = null;

        } elseif ($this->getOkFrame) {
            $content = $this->bodyBuffer->consume($this->bodyBuffer->getLength());

            // deferred has to be first nullified and then resolved, otherwise results in race condition
            $deferred = $this->getDeferred;
            $this->getDeferred = null;
            $deferred->resolve(new Message(
                null,
                $this->getOkFrame->deliveryTag,
                $this->getOkFrame->redelivered,
                $this->getOkFrame->exchange,
                $this->getOkFrame->routingKey,
                $this->headerFrame->toArray(),
                $content
            ));

            $this->getOkFrame = null;
            $this->headerFrame = null;

        } else {
            throw new \LogicException("Either return or deliver frame has to be handled here.");
        }
    }
}
