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

final class Parser
{
    /**
     * @var Buffer
     */
    private $buffer;

    public function __construct()
    {
        $this->buffer = new Buffer;
    }

    public function append(string $chunk): void
    {
        $this->buffer->append($chunk);
    }

    /**
     * Consumes AMQP frame from buffer.
     *
     * Returns NULL if there are not enough data to construct whole frame.
     *
     * @throws \PHPinnacle\Buffer\BufferOverflow
     * @throws \PHPinnacle\Ridge\Exception\ProtocolException
     */
    public function parse(): ?Protocol\AbstractFrame
    {
        if($this->buffer->size() < 7)
        {
            return null;
        }

        $size   = $this->buffer->readUint32(3);
        $length = $size + 8;

        if($this->buffer->size() < $length)
        {
            return null;
        }

        $type     = $this->buffer->readUint8();
        $channel  = $this->buffer->readUint16(1);
        $payload  = $this->buffer->read($size, 7);
        $frameEnd = $this->buffer->readUint8($length - 1);

        $this->buffer->discard($length);

        if($frameEnd !== Constants::FRAME_END)
        {
            throw Exception\ProtocolException::invalidFrameEnd($frameEnd);
        }

        switch($type)
        {
            case Constants::FRAME_HEADER:
                $frame = Protocol\ContentHeaderFrame::unpack(new Buffer($payload));

                break;
            case Constants::FRAME_BODY:
                $frame          = new Protocol\ContentBodyFrame;
                $frame->payload = $payload;

                break;
            case Constants::FRAME_METHOD:
                $frame = $this->consumeMethodFrame(new Buffer($payload));

                break;
            case Constants::FRAME_HEARTBEAT:
                $frame = new Protocol\HeartbeatFrame;

                break;
            default:
                throw Exception\ProtocolException::unknownFrameType($type);
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
     * @throws \PHPinnacle\Buffer\BufferOverflow
     * @throws \PHPinnacle\Ridge\Exception\ClassInvalid
     */
    private function consumeMethodFrame(Buffer $buffer): Protocol\MethodFrame
    {
        $classId  = $buffer->consumeUint16();
        $methodId = $buffer->consumeUint16();

        return match ($classId)
        {
            Constants::CLASS_BASIC => $this->consumeBasicFrame($methodId, $buffer),
            Constants::CLASS_CONNECTION => $this->consumeConnectionFrame($methodId, $buffer),
            Constants::CLASS_CHANNEL => $this->consumeChannelFrame($methodId, $buffer),
            Constants::CLASS_EXCHANGE => $this->consumeExchangeFrame($methodId, $buffer),
            Constants::CLASS_QUEUE => $this->consumeQueueFrame($methodId, $buffer),
            Constants::CLASS_TX => $this->consumeTxFrame($methodId),
            Constants::CLASS_CONFIRM => $this->consumeConfirmFrame($methodId, $buffer),
            default => throw new Exception\ClassInvalid($classId),
        };
    }

    /**
     * @return Protocol\MethodFrame
     *
     * @throws \PHPinnacle\Ridge\Exception\MethodInvalid
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    private function consumeBasicFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        return match ($methodId)
        {
            Constants::METHOD_BASIC_DELIVER => Protocol\BasicDeliverFrame::unpack($buffer),
            Constants::METHOD_BASIC_GET => Protocol\BasicGetFrame::unpack($buffer),
            Constants::METHOD_BASIC_GET_OK => Protocol\BasicGetOkFrame::unpack($buffer),
            Constants::METHOD_BASIC_GET_EMPTY => Protocol\BasicGetEmptyFrame::unpack($buffer),
            Constants::METHOD_BASIC_PUBLISH => Protocol\BasicPublishFrame::unpack($buffer),
            Constants::METHOD_BASIC_RETURN => Protocol\BasicReturnFrame::unpack($buffer),
            Constants::METHOD_BASIC_ACK => Protocol\BasicAckFrame::unpack($buffer),
            Constants::METHOD_BASIC_NACK => Protocol\BasicNackFrame::unpack($buffer),
            Constants::METHOD_BASIC_REJECT => Protocol\BasicRejectFrame::unpack($buffer),
            Constants::METHOD_BASIC_QOS => Protocol\BasicQosFrame::unpack($buffer),
            Constants::METHOD_BASIC_QOS_OK => new Protocol\BasicQosOkFrame,
            Constants::METHOD_BASIC_CONSUME => Protocol\BasicConsumeFrame::unpack($buffer),
            Constants::METHOD_BASIC_CONSUME_OK => Protocol\BasicConsumeOkFrame::unpack($buffer),
            Constants::METHOD_BASIC_CANCEL => Protocol\BasicCancelFrame::unpack($buffer),
            Constants::METHOD_BASIC_CANCEL_OK => Protocol\BasicCancelOkFrame::unpack($buffer),
            Constants::METHOD_BASIC_RECOVER => Protocol\BasicRecoverFrame::unpack($buffer),
            Constants::METHOD_BASIC_RECOVER_OK => new Protocol\BasicRecoverOkFrame,
            Constants::METHOD_BASIC_RECOVER_ASYNC => Protocol\BasicRecoverAsyncFrame::unpack($buffer),
            default => throw new Exception\MethodInvalid(Constants::CLASS_BASIC, $methodId),
        };
    }

    /**
     * @return Protocol\MethodFrame
     *
     * @throws \PHPinnacle\Ridge\Exception\MethodInvalid
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    private function consumeConnectionFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        return match ($methodId)
        {
            Constants::METHOD_CONNECTION_START => Protocol\ConnectionStartFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_START_OK => Protocol\ConnectionStartOkFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_SECURE => Protocol\ConnectionSecureFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_SECURE_OK => Protocol\ConnectionSecureOkFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_TUNE => Protocol\ConnectionTuneFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_TUNE_OK => Protocol\ConnectionTuneOkFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_OPEN => Protocol\ConnectionOpenFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_OPEN_OK => Protocol\ConnectionOpenOkFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_CLOSE => Protocol\ConnectionCloseFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_CLOSE_OK => new Protocol\ConnectionCloseOkFrame,
            Constants::METHOD_CONNECTION_BLOCKED => Protocol\ConnectionBlockedFrame::unpack($buffer),
            Constants::METHOD_CONNECTION_UNBLOCKED => new Protocol\ConnectionUnblockedFrame,
            default => throw new Exception\MethodInvalid(Constants::CLASS_CONNECTION, $methodId),
        };
    }

    /**
     * @return Protocol\MethodFrame
     *
     * @throws \PHPinnacle\Ridge\Exception\MethodInvalid
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    private function consumeChannelFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        return match ($methodId)
        {
            Constants::METHOD_CHANNEL_OPEN => Protocol\ChannelOpenFrame::unpack($buffer),
            Constants::METHOD_CHANNEL_OPEN_OK => Protocol\ChannelOpenOkFrame::unpack($buffer),
            Constants::METHOD_CHANNEL_FLOW => Protocol\ChannelFlowFrame::unpack($buffer),
            Constants::METHOD_CHANNEL_FLOW_OK => Protocol\ChannelFlowOkFrame::unpack($buffer),
            Constants::METHOD_CHANNEL_CLOSE => Protocol\ChannelCloseFrame::unpack($buffer),
            Constants::METHOD_CHANNEL_CLOSE_OK => new Protocol\ChannelCloseOkFrame,
            default => throw new Exception\MethodInvalid(Constants::CLASS_CHANNEL, $methodId),
        };
    }

    /**
     * @return Protocol\MethodFrame
     *
     * @throws \PHPinnacle\Ridge\Exception\MethodInvalid
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    private function consumeExchangeFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        return match ($methodId)
        {
            Constants::METHOD_EXCHANGE_DECLARE => Protocol\ExchangeDeclareFrame::unpack($buffer),
            Constants::METHOD_EXCHANGE_DECLARE_OK => new Protocol\ExchangeDeclareOkFrame,
            Constants::METHOD_EXCHANGE_DELETE => Protocol\ExchangeDeleteFrame::unpack($buffer),
            Constants::METHOD_EXCHANGE_DELETE_OK => new Protocol\ExchangeDeleteOkFrame,
            Constants::METHOD_EXCHANGE_BIND => Protocol\ExchangeBindFrame::unpack($buffer),
            Constants::METHOD_EXCHANGE_BIND_OK => new Protocol\ExchangeBindOkFrame,
            Constants::METHOD_EXCHANGE_UNBIND => Protocol\ExchangeUnbindFrame::unpack($buffer),
            Constants::METHOD_EXCHANGE_UNBIND_OK => new Protocol\ExchangeUnbindOkFrame,
            default => throw new Exception\MethodInvalid(Constants::CLASS_EXCHANGE, $methodId),
        };
    }

    /**
     * @return Protocol\MethodFrame
     *
     * @throws \PHPinnacle\Ridge\Exception\MethodInvalid
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    private function consumeQueueFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        return match ($methodId)
        {
            Constants::METHOD_QUEUE_DECLARE => Protocol\QueueDeclareFrame::unpack($buffer),
            Constants::METHOD_QUEUE_DECLARE_OK => Protocol\QueueDeclareOkFrame::unpack($buffer),
            Constants::METHOD_QUEUE_BIND => Protocol\QueueBindFrame::unpack($buffer),
            Constants::METHOD_QUEUE_BIND_OK => new Protocol\QueueBindOkFrame,
            Constants::METHOD_QUEUE_UNBIND => Protocol\QueueUnbindFrame::unpack($buffer),
            Constants::METHOD_QUEUE_UNBIND_OK => new Protocol\QueueUnbindOkFrame,
            Constants::METHOD_QUEUE_PURGE => Protocol\QueuePurgeFrame::unpack($buffer),
            Constants::METHOD_QUEUE_PURGE_OK => Protocol\QueuePurgeOkFrame::unpack($buffer),
            Constants::METHOD_QUEUE_DELETE => Protocol\QueueDeleteFrame::unpack($buffer),
            Constants::METHOD_QUEUE_DELETE_OK => Protocol\QueueDeleteOkFrame::unpack($buffer),
            default => throw new Exception\MethodInvalid(Constants::CLASS_QUEUE, $methodId),
        };
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\MethodInvalid
     */
    private function consumeTxFrame(int $methodId): Protocol\MethodFrame
    {
        return match ($methodId)
        {
            Constants::METHOD_TX_SELECT => new Protocol\TxSelectFrame,
            Constants::METHOD_TX_SELECT_OK => new Protocol\TxSelectOkFrame,
            Constants::METHOD_TX_COMMIT => new Protocol\TxCommitFrame,
            Constants::METHOD_TX_COMMIT_OK => new Protocol\TxCommitOkFrame,
            Constants::METHOD_TX_ROLLBACK => new Protocol\TxRollbackFrame,
            Constants::METHOD_TX_ROLLBACK_OK => new Protocol\TxRollbackOkFrame,
            default => throw new Exception\MethodInvalid(Constants::CLASS_TX, $methodId),
        };
    }

    /**
     * @throws \PHPinnacle\Ridge\Exception\MethodInvalid
     * @throws \PHPinnacle\Buffer\BufferOverflow
     */
    private function consumeConfirmFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        return match ($methodId)
        {
            Constants::METHOD_CONFIRM_SELECT => Protocol\ConfirmSelectFrame::unpack($buffer),
            Constants::METHOD_CONFIRM_SELECT_OK => new Protocol\ConfirmSelectOkFrame,
            default => throw new Exception\MethodInvalid(Constants::CLASS_CONFIRM, $methodId),
        };
    }
}
