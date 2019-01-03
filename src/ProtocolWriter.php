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

use PHPinnacle\Ridge\Protocol\AbstractFrame;
use PHPinnacle\Ridge\Protocol\ContentBodyFrame;
use PHPinnacle\Ridge\Protocol\ContentHeaderFrame;
use PHPinnacle\Ridge\Protocol\HeartbeatFrame;
use PHPinnacle\Ridge\Protocol\MethodFrame;

class ProtocolWriter
{
    /**
     * Appends AMQP frame to buffer.
     *
     * @param AbstractFrame $frame
     * @param Buffer $buffer
     */
    public function appendFrame(AbstractFrame $frame, Buffer $buffer)
    {
        if ($frame instanceof MethodFrame && $frame->payload !== null) {
            // payload already supplied
        } elseif ($frame instanceof MethodFrame) {
            $frameBuffer = new Buffer();

            $this->appendMethodFrame($frame, $frameBuffer);

            $frame->size    = $frameBuffer->getLength();
            $frame->payload = $frameBuffer;

        } elseif ($frame instanceof ContentHeaderFrame) {
            $frameBuffer = new Buffer();
            // see https://github.com/pika/pika/blob/master/pika/spec.py class BasicProperties
            $frameBuffer->appendUint16($frame->classId);
            $frameBuffer->appendUint16($frame->weight);
            $frameBuffer->appendUint64($frame->bodySize);

            $flags = $frame->flags;

            $frameBuffer->appendUint16($flags);

            if ($flags & ContentHeaderFrame::FLAG_CONTENT_TYPE) {
                $frameBuffer->appendUint8(\strlen($frame->contentType));
                $frameBuffer->append($frame->contentType);
            }

            if ($flags & ContentHeaderFrame::FLAG_CONTENT_ENCODING) {
                $frameBuffer->appendUint8(\strlen($frame->contentEncoding));
                $frameBuffer->append($frame->contentEncoding);
            }

            if ($flags & ContentHeaderFrame::FLAG_HEADERS) {
                $frameBuffer->appendTable($frame->headers);
            }

            if ($flags & ContentHeaderFrame::FLAG_DELIVERY_MODE) {
                $frameBuffer->appendUint8($frame->deliveryMode);
            }

            if ($flags & ContentHeaderFrame::FLAG_PRIORITY) {
                $frameBuffer->appendUint8($frame->priority);
            }

            if ($flags & ContentHeaderFrame::FLAG_CORRELATION_ID) {
                $frameBuffer->appendUint8(\strlen($frame->correlationId));
                $frameBuffer->append($frame->correlationId);
            }

            if ($flags & ContentHeaderFrame::FLAG_REPLY_TO) {
                $frameBuffer->appendUint8(\strlen($frame->replyTo));
                $frameBuffer->append($frame->replyTo);
            }

            if ($flags & ContentHeaderFrame::FLAG_EXPIRATION) {
                $frameBuffer->appendUint8(\strlen($frame->expiration));
                $frameBuffer->append($frame->expiration);
            }

            if ($flags & ContentHeaderFrame::FLAG_MESSAGE_ID) {
                $frameBuffer->appendUint8(\strlen($frame->messageId));
                $frameBuffer->append($frame->messageId);
            }

            if ($flags & ContentHeaderFrame::FLAG_TIMESTAMP) {
                $frameBuffer->appendTimestamp($frame->timestamp);
            }

            if ($flags & ContentHeaderFrame::FLAG_TYPE) {
                $frameBuffer->appendUint8(\strlen($frame->typeHeader));
                $frameBuffer->append($frame->typeHeader);
            }

            if ($flags & ContentHeaderFrame::FLAG_USER_ID) {
                $frameBuffer->appendUint8(\strlen($frame->userId));
                $frameBuffer->append($frame->userId);
            }

            if ($flags & ContentHeaderFrame::FLAG_APP_ID) {
                $frameBuffer->appendUint8(\strlen($frame->appId));
                $frameBuffer->append($frame->appId);
            }

            if ($flags & ContentHeaderFrame::FLAG_CLUSTER_ID) {
                $frameBuffer->appendUint8(\strlen($frame->clusterId));
                $frameBuffer->append($frame->clusterId);
            }

            $frame->size    = $frameBuffer->getLength();
            $frame->payload = $frameBuffer;
        } elseif ($frame instanceof ContentBodyFrame) {
            // body frame's payload is already loaded
        } elseif ($frame instanceof HeartbeatFrame) {
            // heartbeat frame is empty
        } else {
            throw new Exception\ProtocolException("Unhandled frame '" . get_class($frame) . "'.");
        }

        $buffer->appendUint8($frame->type);
        $buffer->appendUint16($frame->channel);
        $buffer->appendUint32($frame->size);
        $buffer->append($frame->payload);
        $buffer->appendUint8(Constants::FRAME_END);
    }

    /**
     * @param Protocol\MethodFrame $frame
     * @param Buffer               $buffer
     */
    private function appendMethodFrame(Protocol\MethodFrame $frame, Buffer $buffer): void
    {
        $buffer->appendUint16($frame->classId);
        $buffer->appendUint16($frame->methodId);

        if ($frame instanceof Protocol\ConnectionStartFrame) {
            $buffer->appendUint8($frame->versionMajor);
            $buffer->appendUint8($frame->versionMinor);
            $buffer->appendTable($frame->serverProperties);
            $buffer->appendUint32(\strlen($frame->mechanisms)); $buffer->append($frame->mechanisms);
            $buffer->appendUint32(\strlen($frame->locales)); $buffer->append($frame->locales);
        } elseif ($frame instanceof Protocol\ConnectionStartOkFrame) {
            $buffer->appendTable($frame->clientProperties);
            $buffer->appendUint8(\strlen($frame->mechanism)); $buffer->append($frame->mechanism);
            $buffer->appendUint32(\strlen($frame->response)); $buffer->append($frame->response);
            $buffer->appendUint8(\strlen($frame->locale)); $buffer->append($frame->locale);
        } elseif ($frame instanceof Protocol\ConnectionSecureFrame) {
            $buffer->appendUint32(\strlen($frame->challenge)); $buffer->append($frame->challenge);
        } elseif ($frame instanceof Protocol\ConnectionSecureOkFrame) {
            $buffer->appendUint32(\strlen($frame->response)); $buffer->append($frame->response);
        } elseif ($frame instanceof Protocol\ConnectionTuneFrame) {
            $buffer->appendInt16($frame->channelMax);
            $buffer->appendInt32($frame->frameMax);
            $buffer->appendInt16($frame->heartbeat);
        } elseif ($frame instanceof Protocol\ConnectionTuneOkFrame) {
            $buffer->appendInt16($frame->channelMax);
            $buffer->appendInt32($frame->frameMax);
            $buffer->appendInt16($frame->heartbeat);
        } elseif ($frame instanceof Protocol\ConnectionOpenFrame) {
            $buffer->appendUint8(\strlen($frame->virtualHost)); $buffer->append($frame->virtualHost);
            $buffer->appendUint8(\strlen($frame->capabilities)); $buffer->append($frame->capabilities);
            $buffer->appendBits([$frame->insist]);
        } elseif ($frame instanceof Protocol\ConnectionOpenOkFrame) {
            $buffer->appendUint8(\strlen($frame->knownHosts)); $buffer->append($frame->knownHosts);
        } elseif ($frame instanceof Protocol\ConnectionCloseFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendUint8(\strlen($frame->replyText)); $buffer->append($frame->replyText);
            $buffer->appendInt16($frame->closeClassId);
            $buffer->appendInt16($frame->closeMethodId);
        } elseif ($frame instanceof Protocol\ConnectionCloseOkFrame) {
        } elseif ($frame instanceof Protocol\ConnectionBlockedFrame) {
            $buffer->appendUint8(\strlen($frame->reason)); $buffer->append($frame->reason);
        } elseif ($frame instanceof Protocol\ConnectionUnblockedFrame) {
        } elseif ($frame instanceof Protocol\ChannelOpenFrame) {
            $buffer->appendUint8(\strlen($frame->outOfBand)); $buffer->append($frame->outOfBand);
        } elseif ($frame instanceof Protocol\ChannelOpenOkFrame) {
            $buffer->appendUint32(\strlen($frame->channelId)); $buffer->append($frame->channelId);
        } elseif ($frame instanceof Protocol\ChannelFlowFrame) {
            $buffer->appendBits([$frame->active]);
        } elseif ($frame instanceof Protocol\ChannelFlowOkFrame) {
            $buffer->appendBits([$frame->active]);
        } elseif ($frame instanceof Protocol\ChannelCloseFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendUint8(\strlen($frame->replyText)); $buffer->append($frame->replyText);
            $buffer->appendInt16($frame->closeClassId);
            $buffer->appendInt16($frame->closeMethodId);
        } elseif ($frame instanceof Protocol\ChannelCloseOkFrame) {
        } elseif ($frame instanceof Protocol\AccessRequestFrame) {
            $buffer->appendUint8(\strlen($frame->realm)); $buffer->append($frame->realm);
            $buffer->appendBits([$frame->exclusive, $frame->passive, $frame->active, $frame->write, $frame->read]);
        } elseif ($frame instanceof Protocol\AccessRequestOkFrame) {
            $buffer->appendInt16($frame->reserved1);
        } elseif ($frame instanceof Protocol\ExchangeDeclareFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(\strlen($frame->exchangeType)); $buffer->append($frame->exchangeType);
            $buffer->appendBits([$frame->passive, $frame->durable, $frame->autoDelete, $frame->internal, $frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\ExchangeDeclareOkFrame) {
        } elseif ($frame instanceof Protocol\ExchangeDeleteFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendBits([$frame->ifUnused, $frame->nowait]);
        } elseif ($frame instanceof Protocol\ExchangeDeleteOkFrame) {
        } elseif ($frame instanceof Protocol\ExchangeBindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->destination)); $buffer->append($frame->destination);
            $buffer->appendUint8(\strlen($frame->source)); $buffer->append($frame->source);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $buffer->appendBits([$frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\ExchangeBindOkFrame) {
        } elseif ($frame instanceof Protocol\ExchangeUnbindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->destination)); $buffer->append($frame->destination);
            $buffer->appendUint8(\strlen($frame->source)); $buffer->append($frame->source);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $buffer->appendBits([$frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\ExchangeUnbindOkFrame) {
        } elseif ($frame instanceof Protocol\QueueDeclareFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendBits([$frame->passive, $frame->durable, $frame->exclusive, $frame->autoDelete, $frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\QueueDeclareOkFrame) {
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendInt32($frame->messageCount);
            $buffer->appendInt32($frame->consumerCount);
        } elseif ($frame instanceof Protocol\QueueBindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $buffer->appendBits([$frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\QueueBindOkFrame) {
        } elseif ($frame instanceof Protocol\QueuePurgeFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendBits([$frame->nowait]);
        } elseif ($frame instanceof Protocol\QueuePurgeOkFrame) {
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof Protocol\QueueDeleteFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendBits([$frame->ifUnused, $frame->ifEmpty, $frame->nowait]);
        } elseif ($frame instanceof Protocol\QueueDeleteOkFrame) {
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof Protocol\QueueUnbindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\QueueUnbindOkFrame) {
        } elseif ($frame instanceof Protocol\BasicQosFrame) {
            $buffer->appendInt32($frame->prefetchSize);
            $buffer->appendInt16($frame->prefetchCount);
            $buffer->appendBits([$frame->global]);
        } elseif ($frame instanceof Protocol\BasicQosOkFrame) {
        } elseif ($frame instanceof Protocol\BasicConsumeFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendUint8(\strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
            $buffer->appendBits([$frame->noLocal, $frame->noAck, $frame->exclusive, $frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\BasicConsumeOkFrame) {
            $buffer->appendUint8(\strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
        } elseif ($frame instanceof Protocol\BasicCancelFrame) {
            $buffer->appendUint8(\strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
            $buffer->appendBits([$frame->nowait]);
        } elseif ($frame instanceof Protocol\BasicCancelOkFrame) {
            $buffer->appendUint8(\strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
        } elseif ($frame instanceof Protocol\BasicPublishFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $buffer->appendBits([$frame->mandatory, $frame->immediate]);
        } elseif ($frame instanceof Protocol\BasicReturnFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendUint8(\strlen($frame->replyText)); $buffer->append($frame->replyText);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
        } elseif ($frame instanceof Protocol\BasicDeliverFrame) {
            $buffer->appendUint8(\strlen($frame->consumerTag)); $buffer->append($frame->consumerTag);
            $buffer->appendInt64($frame->deliveryTag);
            $buffer->appendBits([$frame->redelivered]);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
        } elseif ($frame instanceof Protocol\BasicGetFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendUint8(\strlen($frame->queue)); $buffer->append($frame->queue);
            $buffer->appendBits([$frame->noAck]);
        } elseif ($frame instanceof Protocol\BasicGetOkFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $buffer->appendBits([$frame->redelivered]);
            $buffer->appendUint8(\strlen($frame->exchange)); $buffer->append($frame->exchange);
            $buffer->appendUint8(\strlen($frame->routingKey)); $buffer->append($frame->routingKey);
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof Protocol\BasicGetEmptyFrame) {
            $buffer->appendUint8(\strlen($frame->clusterId)); $buffer->append($frame->clusterId);
        } elseif ($frame instanceof Protocol\BasicAckFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $buffer->appendBits([$frame->multiple]);
        } elseif ($frame instanceof Protocol\BasicRejectFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $buffer->appendBits([$frame->requeue]);
        } elseif ($frame instanceof Protocol\BasicRecoverAsyncFrame) {
            $buffer->appendBits([$frame->requeue]);
        } elseif ($frame instanceof Protocol\BasicRecoverFrame) {
            $buffer->appendBits([$frame->requeue]);
        } elseif ($frame instanceof Protocol\BasicRecoverOkFrame) {
        } elseif ($frame instanceof Protocol\BasicNackFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $buffer->appendBits([$frame->multiple, $frame->requeue]);
        } elseif ($frame instanceof Protocol\TxSelectFrame) {
        } elseif ($frame instanceof Protocol\TxSelectOkFrame) {
        } elseif ($frame instanceof Protocol\TxCommitFrame) {
        } elseif ($frame instanceof Protocol\TxCommitOkFrame) {
        } elseif ($frame instanceof Protocol\TxRollbackFrame) {
        } elseif ($frame instanceof Protocol\TxRollbackOkFrame) {
        } elseif ($frame instanceof Protocol\ConfirmSelectFrame) {
            $buffer->appendBits([$frame->nowait]);
        } elseif ($frame instanceof Protocol\ConfirmSelectOkFrame) {
        } else {
            throw new Exception\ProtocolException('Unhandled method frame ' . get_class($frame) . '.');
        }
    }
}
