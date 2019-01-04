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

final class ProtocolWriter
{
    /**
     * Appends AMQP frame to buffer.
     *
     * @param AbstractFrame $frame
     *
     * @return Buffer
     */
    public static function buffer(AbstractFrame $frame): Buffer
    {
        if ($frame instanceof MethodFrame && $frame->payload !== null) {
            // payload already supplied
        } elseif ($frame instanceof MethodFrame) {
            $frameBuffer = self::bufferMethodFrame($frame);

            $frame->size    = $frameBuffer->size();
            $frame->payload = $frameBuffer;

        } elseif ($frame instanceof ContentHeaderFrame) {
            $frameBuffer = self::bufferHeaderFrame($frame);

            $frame->size    = $frameBuffer->size();
            $frame->payload = $frameBuffer;
        } elseif ($frame instanceof ContentBodyFrame) {
            // body frame's payload is already loaded
        } elseif ($frame instanceof HeartbeatFrame) {
            // heartbeat frame is empty
        } else {
            throw Exception\ProtocolException::unknownFrameClass($frame);
        }

        $buffer = new Buffer;
        $buffer
            ->appendUint8($frame->type)
            ->appendUint16($frame->channel)
            ->appendUint32($frame->size)
            ->append($frame->payload)
            ->appendUint8(Constants::FRAME_END)
        ;

        return $buffer;
    }

    /**
     * @param Protocol\ContentHeaderFrame $frame
     *
     * @return Buffer
     */
    private static function bufferHeaderFrame(Protocol\ContentHeaderFrame $frame): Buffer
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint16($frame->classId)
            ->appendUint16($frame->weight)
            ->appendUint64($frame->bodySize)
        ;

        $flags = $frame->flags;

        $buffer->appendUint16($flags);

        if ($flags & ContentHeaderFrame::FLAG_CONTENT_TYPE) {
            $buffer->appendString($frame->contentType);
        }

        if ($flags & ContentHeaderFrame::FLAG_CONTENT_ENCODING) {
            $buffer->appendString($frame->contentEncoding);
        }

        if ($flags & ContentHeaderFrame::FLAG_HEADERS) {
            $buffer->appendTable($frame->headers);
        }

        if ($flags & ContentHeaderFrame::FLAG_DELIVERY_MODE) {
            $buffer->appendUint8($frame->deliveryMode);
        }

        if ($flags & ContentHeaderFrame::FLAG_PRIORITY) {
            $buffer->appendUint8($frame->priority);
        }

        if ($flags & ContentHeaderFrame::FLAG_CORRELATION_ID) {
            $buffer->appendString($frame->correlationId);
        }

        if ($flags & ContentHeaderFrame::FLAG_REPLY_TO) {
            $buffer->appendString($frame->replyTo);
        }

        if ($flags & ContentHeaderFrame::FLAG_EXPIRATION) {
            $buffer->appendString($frame->expiration);
        }

        if ($flags & ContentHeaderFrame::FLAG_MESSAGE_ID) {
            $buffer->appendString($frame->messageId);
        }

        if ($flags & ContentHeaderFrame::FLAG_TIMESTAMP) {
            $buffer->appendTimestamp($frame->timestamp);
        }

        if ($flags & ContentHeaderFrame::FLAG_TYPE) {
            $buffer->appendString($frame->typeHeader);
        }

        if ($flags & ContentHeaderFrame::FLAG_USER_ID) {
            $buffer->appendString($frame->userId);
        }

        if ($flags & ContentHeaderFrame::FLAG_APP_ID) {
            $buffer->appendString($frame->appId);
        }

        if ($flags & ContentHeaderFrame::FLAG_CLUSTER_ID) {
            $buffer->appendString($frame->clusterId);
        }

        return $buffer;
    }

    /**
     * @param Protocol\MethodFrame $frame
     *
     * @return Buffer
     */
    private static function bufferMethodFrame(Protocol\MethodFrame $frame): Buffer
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint16($frame->classId)
            ->appendUint16($frame->methodId)
        ;

        if ($frame instanceof Protocol\ConnectionStartFrame) {
            $buffer->appendUint8($frame->versionMajor);
            $buffer->appendUint8($frame->versionMinor);
            $buffer->appendTable($frame->serverProperties);
            $buffer->appendText($frame->mechanisms);
            $buffer->appendText($frame->locales);
        } elseif ($frame instanceof Protocol\ConnectionStartOkFrame) {
            $buffer->appendTable($frame->clientProperties);
            $buffer->appendString($frame->mechanism);
            $buffer->appendText($frame->response);
            $buffer->appendString($frame->locale);
        } elseif ($frame instanceof Protocol\ConnectionSecureFrame) {
            $buffer->appendText($frame->challenge);
        } elseif ($frame instanceof Protocol\ConnectionSecureOkFrame) {
            $buffer->appendText($frame->response);
        } elseif ($frame instanceof Protocol\ConnectionTuneFrame) {
            $buffer->appendInt16($frame->channelMax);
            $buffer->appendInt32($frame->frameMax);
            $buffer->appendInt16($frame->heartbeat);
        } elseif ($frame instanceof Protocol\ConnectionTuneOkFrame) {
            $buffer->appendInt16($frame->channelMax);
            $buffer->appendInt32($frame->frameMax);
            $buffer->appendInt16($frame->heartbeat);
        } elseif ($frame instanceof Protocol\ConnectionOpenFrame) {
            $buffer->appendString($frame->virtualHost);
            $buffer->appendString($frame->capabilities);
            $buffer->appendBits([$frame->insist]);
        } elseif ($frame instanceof Protocol\ConnectionOpenOkFrame) {
            $buffer->appendString($frame->knownHosts);
        } elseif ($frame instanceof Protocol\ConnectionCloseFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendString($frame->replyText);
            $buffer->appendInt16($frame->closeClassId);
            $buffer->appendInt16($frame->closeMethodId);
        } elseif ($frame instanceof Protocol\ConnectionCloseOkFrame) {
        } elseif ($frame instanceof Protocol\ConnectionBlockedFrame) {
            $buffer->appendString($frame->reason);
        } elseif ($frame instanceof Protocol\ConnectionUnblockedFrame) {
        } elseif ($frame instanceof Protocol\ChannelOpenFrame) {
            $buffer->appendString($frame->outOfBand);
        } elseif ($frame instanceof Protocol\ChannelOpenOkFrame) {
            $buffer->appendText($frame->channelId);
        } elseif ($frame instanceof Protocol\ChannelFlowFrame) {
            $buffer->appendBits([$frame->active]);
        } elseif ($frame instanceof Protocol\ChannelFlowOkFrame) {
            $buffer->appendBits([$frame->active]);
        } elseif ($frame instanceof Protocol\ChannelCloseFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendString($frame->replyText);
            $buffer->appendInt16($frame->closeClassId);
            $buffer->appendInt16($frame->closeMethodId);
        } elseif ($frame instanceof Protocol\ChannelCloseOkFrame) {
        } elseif ($frame instanceof Protocol\AccessRequestFrame) {
            $buffer->appendString($frame->realm);
            $buffer->appendBits([$frame->exclusive, $frame->passive, $frame->active, $frame->write, $frame->read]);
        } elseif ($frame instanceof Protocol\AccessRequestOkFrame) {
            $buffer->appendInt16($frame->reserved1);
        } elseif ($frame instanceof Protocol\ExchangeDeclareFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->exchange);
            $buffer->appendString($frame->exchangeType);
            $buffer->appendBits([$frame->passive, $frame->durable, $frame->autoDelete, $frame->internal, $frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\ExchangeDeclareOkFrame) {
        } elseif ($frame instanceof Protocol\ExchangeDeleteFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->exchange);
            $buffer->appendBits([$frame->ifUnused, $frame->nowait]);
        } elseif ($frame instanceof Protocol\ExchangeDeleteOkFrame) {
        } elseif ($frame instanceof Protocol\ExchangeBindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->destination);
            $buffer->appendString($frame->source);
            $buffer->appendString($frame->routingKey);
            $buffer->appendBits([$frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\ExchangeBindOkFrame) {
        } elseif ($frame instanceof Protocol\ExchangeUnbindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->destination);
            $buffer->appendString($frame->source);
            $buffer->appendString($frame->routingKey);
            $buffer->appendBits([$frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\ExchangeUnbindOkFrame) {
        } elseif ($frame instanceof Protocol\QueueDeclareFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->queue);
            $buffer->appendBits([$frame->passive, $frame->durable, $frame->exclusive, $frame->autoDelete, $frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\QueueDeclareOkFrame) {
            $buffer->appendString($frame->queue);
            $buffer->appendInt32($frame->messageCount);
            $buffer->appendInt32($frame->consumerCount);
        } elseif ($frame instanceof Protocol\QueueBindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->queue);
            $buffer->appendString($frame->exchange);
            $buffer->appendString($frame->routingKey);
            $buffer->appendBits([$frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\QueueBindOkFrame) {
        } elseif ($frame instanceof Protocol\QueuePurgeFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->queue);
            $buffer->appendBits([$frame->nowait]);
        } elseif ($frame instanceof Protocol\QueuePurgeOkFrame) {
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof Protocol\QueueDeleteFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->queue);
            $buffer->appendBits([$frame->ifUnused, $frame->ifEmpty, $frame->nowait]);
        } elseif ($frame instanceof Protocol\QueueDeleteOkFrame) {
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof Protocol\QueueUnbindFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->queue);
            $buffer->appendString($frame->exchange);
            $buffer->appendString($frame->routingKey);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\QueueUnbindOkFrame) {
        } elseif ($frame instanceof Protocol\BasicQosFrame) {
            $buffer->appendInt32($frame->prefetchSize);
            $buffer->appendInt16($frame->prefetchCount);
            $buffer->appendBits([$frame->global]);
        } elseif ($frame instanceof Protocol\BasicQosOkFrame) {
        } elseif ($frame instanceof Protocol\BasicConsumeFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->queue);
            $buffer->appendString($frame->consumerTag);
            $buffer->appendBits([$frame->noLocal, $frame->noAck, $frame->exclusive, $frame->nowait]);
            $buffer->appendTable($frame->arguments);
        } elseif ($frame instanceof Protocol\BasicConsumeOkFrame) {
            $buffer->appendString($frame->consumerTag);
        } elseif ($frame instanceof Protocol\BasicCancelFrame) {
            $buffer->appendString($frame->consumerTag);
            $buffer->appendBits([$frame->nowait]);
        } elseif ($frame instanceof Protocol\BasicCancelOkFrame) {
            $buffer->appendString($frame->consumerTag);
        } elseif ($frame instanceof Protocol\BasicPublishFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->exchange);
            $buffer->appendString($frame->routingKey);
            $buffer->appendBits([$frame->mandatory, $frame->immediate]);
        } elseif ($frame instanceof Protocol\BasicReturnFrame) {
            $buffer->appendInt16($frame->replyCode);
            $buffer->appendString($frame->replyText);
            $buffer->appendString($frame->exchange);
            $buffer->appendString($frame->routingKey);
        } elseif ($frame instanceof Protocol\BasicDeliverFrame) {
            $buffer->appendString($frame->consumerTag);
            $buffer->appendInt64($frame->deliveryTag);
            $buffer->appendBits([$frame->redelivered]);
            $buffer->appendString($frame->exchange);
            $buffer->appendString($frame->routingKey);
        } elseif ($frame instanceof Protocol\BasicGetFrame) {
            $buffer->appendInt16($frame->reserved1);
            $buffer->appendString($frame->queue);
            $buffer->appendBits([$frame->noAck]);
        } elseif ($frame instanceof Protocol\BasicGetOkFrame) {
            $buffer->appendInt64($frame->deliveryTag);
            $buffer->appendBits([$frame->redelivered]);
            $buffer->appendString($frame->exchange);
            $buffer->appendString($frame->routingKey);
            $buffer->appendInt32($frame->messageCount);
        } elseif ($frame instanceof Protocol\BasicGetEmptyFrame) {
            $buffer->appendString($frame->clusterId);
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

        return $buffer;
    }
}
