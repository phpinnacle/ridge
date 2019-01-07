<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPinnacle\Ridge;

class ProtocolReader
{
    /**
     * Consumes AMQP frame from buffer.
     *
     * Returns NULL if there are not enough data to construct whole frame.
     *
     * @param Buffer $buffer
     *
     * @return Protocol\AbstractFrame
     */
    public static function frame(Buffer $buffer): ?Protocol\AbstractFrame
    {
        // not enough data
        if ($buffer->size() < 7) {
            return null;
        }

        $type    = $buffer->readUint8(0);
        $channel = $buffer->readUint16(1);
        $size    = $buffer->readUint32(3);

        $payloadOffset = 7; // type:uint8=>1 + channel:uint16=>2 + size:uint32=>4 ==> 7

        // not enough data
        if ($buffer->size() < $payloadOffset + $size + 1 /* frame end byte */) {
            return null;
        }

        $buffer->consume(7);

        $payload  = $buffer->consume($size);
        $frameEnd = $buffer->consumeUint8();

        if ($frameEnd !== Constants::FRAME_END) {
            throw Exception\ProtocolException::invalidFrameEnd($frameEnd);
        }

        $frameBuffer = new Buffer($payload);

        switch ($type) {
            case Constants::FRAME_METHOD:
                $frame = self::consumeMethodFrame($frameBuffer);

                break;
            case Constants::FRAME_HEADER:
                $frame = self::consumeHeaderFrame($frameBuffer);

                break;
            case Constants::FRAME_BODY:
                $frame = new Protocol\ContentBodyFrame;
                $frame->payload = $frameBuffer->consume($frameBuffer->size());

                break;
            case Constants::FRAME_HEARTBEAT:
                $frame = new Protocol\HeartbeatFrame;

                if (!$frameBuffer->empty()) {
                    throw Exception\ProtocolException::notEmptyHeartbeat();
                }

                break;
            default:
                throw Exception\ProtocolException::unknownFrameType($type);
        }

        if (!$frameBuffer->empty()) {
            throw new Exception\ProtocolException("Frame buffer not entirely consumed.");
        }

        /** @var Protocol\AbstractFrame $frame */
        $frame->type    = $type;
        $frame->size    = $size;
        $frame->channel = $channel;

        return $frame;
    }

    /**
     * Consumes AMQP method frame.
     *
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private static function consumeMethodFrame(Buffer $buffer): Protocol\MethodFrame
    {
        $classId  = $buffer->consumeUint16();
        $methodId = $buffer->consumeUint16();

        if ($classId === Constants::CLASS_CONNECTION) {
            if ($methodId === Constants::METHOD_CONNECTION_START) {
                $frame = new Protocol\ConnectionStartFrame;
                $frame->versionMajor = $buffer->consumeUint8();
                $frame->versionMinor = $buffer->consumeUint8();
                $frame->serverProperties = $buffer->consumeTable();
                $frame->mechanisms = $buffer->consumeText();
                $frame->locales = $buffer->consumeText();
            } elseif ($methodId === Constants::METHOD_CONNECTION_START_OK) {
                $frame = new Protocol\ConnectionStartOkFrame;
                $frame->clientProperties = $buffer->consumeTable();
                $frame->mechanism = $buffer->consumeString();
                $frame->response = $buffer->consumeText();
                $frame->locale = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_CONNECTION_SECURE) {
                $frame = new Protocol\ConnectionSecureFrame;
                $frame->challenge = $buffer->consumeText();
            } elseif ($methodId === Constants::METHOD_CONNECTION_SECURE_OK) {
                $frame = new Protocol\ConnectionSecureOkFrame;
                $frame->response = $buffer->consumeText();
            } elseif ($methodId === Constants::METHOD_CONNECTION_TUNE) {
                $frame = new Protocol\ConnectionTuneFrame;
                $frame->channelMax = $buffer->consumeInt16();
                $frame->frameMax = $buffer->consumeInt32();
                $frame->heartbeat = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CONNECTION_TUNE_OK) {
                $frame = new Protocol\ConnectionTuneOkFrame;
                $frame->channelMax = $buffer->consumeInt16();
                $frame->frameMax = $buffer->consumeInt32();
                $frame->heartbeat = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CONNECTION_OPEN) {
                $frame = new Protocol\ConnectionOpenFrame;
                $frame->virtualHost = $buffer->consumeString();
                $frame->capabilities = $buffer->consumeString();
                list($frame->insist) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_CONNECTION_OPEN_OK) {
                $frame = new Protocol\ConnectionOpenOkFrame;
                $frame->knownHosts = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_CONNECTION_CLOSE) {
                $frame = new Protocol\ConnectionCloseFrame;
                $frame->replyCode = $buffer->consumeInt16();
                $frame->replyText = $buffer->consumeString();
                $frame->closeClassId = $buffer->consumeInt16();
                $frame->closeMethodId = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CONNECTION_CLOSE_OK) {
                $frame = new Protocol\ConnectionCloseOkFrame;
            } elseif ($methodId === Constants::METHOD_CONNECTION_BLOCKED) {
                $frame = new Protocol\ConnectionBlockedFrame;
                $frame->reason = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_CONNECTION_UNBLOCKED) {
                $frame = new Protocol\ConnectionUnblockedFrame;
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_CHANNEL) {
            if ($methodId === Constants::METHOD_CHANNEL_OPEN) {
                $frame = new Protocol\ChannelOpenFrame;
                $frame->outOfBand = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_CHANNEL_OPEN_OK) {
                $frame = new Protocol\ChannelOpenOkFrame;
                $frame->channelId = $buffer->consumeText();
            } elseif ($methodId === Constants::METHOD_CHANNEL_FLOW) {
                $frame = new Protocol\ChannelFlowFrame;
                list($frame->active) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_CHANNEL_FLOW_OK) {
                $frame = new Protocol\ChannelFlowOkFrame;
                list($frame->active) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_CHANNEL_CLOSE) {
                $frame = new Protocol\ChannelCloseFrame;
                $frame->replyCode = $buffer->consumeInt16();
                $frame->replyText = $buffer->consumeString();
                $frame->closeClassId = $buffer->consumeInt16();
                $frame->closeMethodId = $buffer->consumeInt16();
            } elseif ($methodId === Constants::METHOD_CHANNEL_CLOSE_OK) {
                $frame = new Protocol\ChannelCloseOkFrame;
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_ACCESS) {
            if ($methodId === Constants::METHOD_ACCESS_REQUEST) {
                $frame = new Protocol\AccessRequestFrame;
                $frame->realm = $buffer->consumeString();
                list($frame->exclusive, $frame->passive, $frame->active, $frame->write, $frame->read) = $buffer->consumeBits(5);
            } elseif ($methodId === Constants::METHOD_ACCESS_REQUEST_OK) {
                $frame = new Protocol\AccessRequestOkFrame;
                $frame->reserved1 = $buffer->consumeInt16();
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_EXCHANGE) {
            if ($methodId === Constants::METHOD_EXCHANGE_DECLARE) {
                $frame = new Protocol\ExchangeDeclareFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->exchange = $buffer->consumeString();
                $frame->exchangeType = $buffer->consumeString();
                list($frame->passive, $frame->durable, $frame->autoDelete, $frame->internal, $frame->nowait) = $buffer->consumeBits(5);
                $frame->arguments = $buffer->consumeTable();
            } elseif ($methodId === Constants::METHOD_EXCHANGE_DECLARE_OK) {
                $frame = new Protocol\ExchangeDeclareOkFrame;
            } elseif ($methodId === Constants::METHOD_EXCHANGE_DELETE) {
                $frame = new Protocol\ExchangeDeleteFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->exchange = $buffer->consumeString();
                list($frame->ifUnused, $frame->nowait) = $buffer->consumeBits(2);
            } elseif ($methodId === Constants::METHOD_EXCHANGE_DELETE_OK) {
                $frame = new Protocol\ExchangeDeleteOkFrame;
            } elseif ($methodId === Constants::METHOD_EXCHANGE_BIND) {
                $frame = new Protocol\ExchangeBindFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->destination = $buffer->consumeString();
                $frame->source = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
                list($frame->nowait) = $buffer->consumeBits(1);
                $frame->arguments = $buffer->consumeTable();
            } elseif ($methodId === Constants::METHOD_EXCHANGE_BIND_OK) {
                $frame = new Protocol\ExchangeBindOkFrame;
            } elseif ($methodId === Constants::METHOD_EXCHANGE_UNBIND) {
                $frame = new Protocol\ExchangeUnbindFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->destination = $buffer->consumeString();
                $frame->source = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
                list($frame->nowait) = $buffer->consumeBits(1);
                $frame->arguments = $buffer->consumeTable();
            } elseif ($methodId === Constants::METHOD_EXCHANGE_UNBIND_OK) {
                $frame = new Protocol\ExchangeUnbindOkFrame;
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_QUEUE) {
            if ($methodId === Constants::METHOD_QUEUE_DECLARE) {
                $frame = new Protocol\QueueDeclareFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consumeString();
                list($frame->passive, $frame->durable, $frame->exclusive, $frame->autoDelete, $frame->nowait) = $buffer->consumeBits(5);
                $frame->arguments = $buffer->consumeTable();
            } elseif ($methodId === Constants::METHOD_QUEUE_DECLARE_OK) {
                $frame = new Protocol\QueueDeclareOkFrame;
                $frame->queue = $buffer->consumeString();
                $frame->messageCount = $buffer->consumeInt32();
                $frame->consumerCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_QUEUE_BIND) {
                $frame = new Protocol\QueueBindFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consumeString();
                $frame->exchange = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
                list($frame->nowait) = $buffer->consumeBits(1);
                $frame->arguments = $buffer->consumeTable();
            } elseif ($methodId === Constants::METHOD_QUEUE_BIND_OK) {
                $frame = new Protocol\QueueBindOkFrame;
            } elseif ($methodId === Constants::METHOD_QUEUE_PURGE) {
                $frame = new Protocol\QueuePurgeFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consumeString();
                list($frame->nowait) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_QUEUE_PURGE_OK) {
                $frame = new Protocol\QueuePurgeOkFrame;
                $frame->messageCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_QUEUE_DELETE) {
                $frame = new Protocol\QueueDeleteFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consumeString();
                list($frame->ifUnused, $frame->ifEmpty, $frame->nowait) = $buffer->consumeBits(3);
            } elseif ($methodId === Constants::METHOD_QUEUE_DELETE_OK) {
                $frame = new Protocol\QueueDeleteOkFrame;
                $frame->messageCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_QUEUE_UNBIND) {
                $frame = new Protocol\QueueUnbindFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consumeString();
                $frame->exchange = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
                $frame->arguments = $buffer->consumeTable();
            } elseif ($methodId === Constants::METHOD_QUEUE_UNBIND_OK) {
                $frame = new Protocol\QueueUnbindOkFrame;
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_BASIC) {
            if ($methodId === Constants::METHOD_BASIC_QOS) {
                $frame = new Protocol\BasicQosFrame;
                $frame->prefetchSize = $buffer->consumeInt32();
                $frame->prefetchCount = $buffer->consumeInt16();
                list($frame->global) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_BASIC_QOS_OK) {
                $frame = new Protocol\BasicQosOkFrame;
            } elseif ($methodId === Constants::METHOD_BASIC_CONSUME) {
                $frame = new Protocol\BasicConsumeFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consumeString();
                $frame->consumerTag = $buffer->consumeString();
                list($frame->noLocal, $frame->noAck, $frame->exclusive, $frame->nowait) = $buffer->consumeBits(4);
                $frame->arguments = $buffer->consumeTable();
            } elseif ($methodId === Constants::METHOD_BASIC_CONSUME_OK) {
                $frame = new Protocol\BasicConsumeOkFrame;
                $frame->consumerTag = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_BASIC_CANCEL) {
                $frame = new Protocol\BasicCancelFrame;
                $frame->consumerTag = $buffer->consumeString();
                list($frame->nowait) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_BASIC_CANCEL_OK) {
                $frame = new Protocol\BasicCancelOkFrame;
                $frame->consumerTag = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_BASIC_PUBLISH) {
                $frame = new Protocol\BasicPublishFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->exchange = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
                list($frame->mandatory, $frame->immediate) = $buffer->consumeBits(2);
            } elseif ($methodId === Constants::METHOD_BASIC_RETURN) {
                $frame = new Protocol\BasicReturnFrame;
                $frame->replyCode = $buffer->consumeInt16();
                $frame->replyText = $buffer->consumeString();
                $frame->exchange = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_BASIC_DELIVER) {
                $frame = new Protocol\BasicDeliverFrame;
                $frame->consumerTag = $buffer->consumeString();
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->redelivered) = $buffer->consumeBits(1);
                $frame->exchange = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_BASIC_GET) {
                $frame = new Protocol\BasicGetFrame;
                $frame->reserved1 = $buffer->consumeInt16();
                $frame->queue = $buffer->consumeString();
                list($frame->noAck) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_BASIC_GET_OK) {
                $frame = new Protocol\BasicGetOkFrame;
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->redelivered) = $buffer->consumeBits(1);
                $frame->exchange = $buffer->consumeString();
                $frame->routingKey = $buffer->consumeString();
                $frame->messageCount = $buffer->consumeInt32();
            } elseif ($methodId === Constants::METHOD_BASIC_GET_EMPTY) {
                $frame = new Protocol\BasicGetEmptyFrame;
                $frame->clusterId = $buffer->consumeString();
            } elseif ($methodId === Constants::METHOD_BASIC_ACK) {
                $frame = new Protocol\BasicAckFrame;
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->multiple) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_BASIC_REJECT) {
                $frame = new Protocol\BasicRejectFrame;
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->requeue) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_BASIC_RECOVER_ASYNC) {
                $frame = new Protocol\BasicRecoverAsyncFrame;
                list($frame->requeue) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_BASIC_RECOVER) {
                $frame = new Protocol\BasicRecoverFrame;
                list($frame->requeue) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_BASIC_RECOVER_OK) {
                $frame = new Protocol\BasicRecoverOkFrame;
            } elseif ($methodId === Constants::METHOD_BASIC_NACK) {
                $frame = new Protocol\BasicNackFrame;
                $frame->deliveryTag = $buffer->consumeInt64();
                list($frame->multiple, $frame->requeue) = $buffer->consumeBits(2);
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_TX) {
            if ($methodId === Constants::METHOD_TX_SELECT) {
                $frame = new Protocol\TxSelectFrame;
            } elseif ($methodId === Constants::METHOD_TX_SELECT_OK) {
                $frame = new Protocol\TxSelectOkFrame;
            } elseif ($methodId === Constants::METHOD_TX_COMMIT) {
                $frame = new Protocol\TxCommitFrame;
            } elseif ($methodId === Constants::METHOD_TX_COMMIT_OK) {
                $frame = new Protocol\TxCommitOkFrame;
            } elseif ($methodId === Constants::METHOD_TX_ROLLBACK) {
                $frame = new Protocol\TxRollbackFrame;
            } elseif ($methodId === Constants::METHOD_TX_ROLLBACK_OK) {
                $frame = new Protocol\TxRollbackOkFrame;
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } elseif ($classId === Constants::CLASS_CONFIRM) {
            if ($methodId === Constants::METHOD_CONFIRM_SELECT) {
                $frame = new Protocol\ConfirmSelectFrame;
                list($frame->nowait) = $buffer->consumeBits(1);
            } elseif ($methodId === Constants::METHOD_CONFIRM_SELECT_OK) {
                $frame = new Protocol\ConfirmSelectOkFrame;
            } else {
                throw new Exception\MethodInvalid($classId, $methodId);
            }
        } else {
            throw new Exception\ClassInvalid($classId);
        }

        $frame->classId  = $classId;
        $frame->methodId = $methodId;

        return $frame;
    }

    /**
     * @param Buffer $buffer
     *
     * @return Protocol\ContentHeaderFrame
     */
    private static function consumeHeaderFrame(Buffer $buffer): Protocol\ContentHeaderFrame
    {
        $frame = new Protocol\ContentHeaderFrame;
        $frame->classId  = $buffer->consumeUint16();
        $frame->weight   = $buffer->consumeUint16();
        $frame->bodySize = $buffer->consumeUint64();
        $frame->flags    = $flags = $buffer->consumeUint16();

        if ($flags & Protocol\ContentHeaderFrame::FLAG_CONTENT_TYPE) {
            $frame->contentType = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_CONTENT_ENCODING) {
            $frame->contentEncoding = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_HEADERS) {
            $frame->headers = $buffer->consumeTable();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_DELIVERY_MODE) {
            $frame->deliveryMode = $buffer->consumeUint8();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_PRIORITY) {
            $frame->priority = $buffer->consumeUint8();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_CORRELATION_ID) {
            $frame->correlationId = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_REPLY_TO) {
            $frame->replyTo = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_EXPIRATION) {
            $frame->expiration = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_MESSAGE_ID) {
            $frame->messageId = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_TIMESTAMP) {
            $frame->timestamp = $buffer->consumeTimestamp();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_TYPE) {
            $frame->typeHeader = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_USER_ID) {
            $frame->userId = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_APP_ID) {
            $frame->appId = $buffer->consumeString();
        }

        if ($flags & Protocol\ContentHeaderFrame::FLAG_CLUSTER_ID) {
            $frame->clusterId = $buffer->consumeString();
        }

        return $frame;
    }
}
