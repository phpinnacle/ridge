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

class Parser
{
    /**
     * @var Buffer
     */
    private $buffer;

    public function __construct()
    {
        $this->buffer = new Buffer;
    }

    /**
     * @param string $chunk
     *
     * @return void
     */
    public function append(string $chunk): void
    {
        $this->buffer->append($chunk);
    }

    /**
     * Consumes AMQP frame from buffer.
     *
     * Returns NULL if there are not enough data to construct whole frame.
     *
     * @return Protocol\AbstractFrame
     */
    public function parse(): ?Protocol\AbstractFrame
    {
        if ($this->buffer->size() < 7) {
            return null;
        }

        $type    = $this->buffer->readUint8(0);
        $channel = $this->buffer->readUint16(1);
        $size    = $this->buffer->readUint32(3);

        if ($this->buffer->size() < $size + 8) {
            return null;
        }

        $this->buffer->discard(7);

        $payload  = $this->buffer->consume($size);
        $frameEnd = $this->buffer->consumeUint8();

        if ($frameEnd !== Constants::FRAME_END) {
            throw Exception\ProtocolException::invalidFrameEnd($frameEnd);
        }

        switch ($type) {
            case Constants::FRAME_HEADER:
                $frame = Protocol\ContentHeaderFrame::unpack(new Buffer($payload));

                break;
            case Constants::FRAME_BODY:
                $frame = new Protocol\ContentBodyFrame;
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
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeMethodFrame(Buffer $buffer): Protocol\MethodFrame
    {
        $classId  = $buffer->consumeUint16();
        $methodId = $buffer->consumeUint16();

        switch ($classId) {
            case Constants::CLASS_BASIC:
                return $this->consumeBasicFrame($methodId, $buffer);
            case Constants::CLASS_CONNECTION:
                return $this->consumeConnectionFrame($methodId, $buffer);
            case Constants::CLASS_CHANNEL:
                return $this->consumeChannelFrame($methodId, $buffer);
            case Constants::CLASS_EXCHANGE:
                return $this->consumeExchangeFrame($methodId, $buffer);
            case Constants::CLASS_QUEUE:
                return $this->consumeQueueFrame($methodId, $buffer);
            case Constants::CLASS_TX:
                return $this->consumeTxFrame($methodId);
            case Constants::CLASS_ACCESS:
                return $this->consumeAccessFrame($methodId, $buffer);
            case Constants::CLASS_CONFIRM:
                return $this->consumeConfirmFrame($methodId, $buffer);
            default:
                throw new Exception\ClassInvalid($classId);
        }
    }
    
    /**
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeBasicFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_BASIC_DELIVER:
                return Protocol\BasicDeliverFrame::unpack($buffer);
            case Constants::METHOD_BASIC_GET:
                return Protocol\BasicGetFrame::unpack($buffer);
            case Constants::METHOD_BASIC_GET_OK:
                return Protocol\BasicGetOkFrame::unpack($buffer);
            case Constants::METHOD_BASIC_GET_EMPTY:
                return Protocol\BasicGetEmptyFrame::unpack($buffer);
            case Constants::METHOD_BASIC_PUBLISH:
                return Protocol\BasicPublishFrame::unpack($buffer);
            case Constants::METHOD_BASIC_RETURN:
                return Protocol\BasicReturnFrame::unpack($buffer);
            case Constants::METHOD_BASIC_ACK:
                return Protocol\BasicAckFrame::unpack($buffer);
            case Constants::METHOD_BASIC_NACK:
                return Protocol\BasicNackFrame::unpack($buffer);
            case Constants::METHOD_BASIC_REJECT:
                return Protocol\BasicRejectFrame::unpack($buffer);
            case Constants::METHOD_BASIC_QOS:
                return Protocol\BasicQosFrame::unpack($buffer);
            case Constants::METHOD_BASIC_QOS_OK:
                return new Protocol\BasicQosOkFrame;
            case Constants::METHOD_BASIC_CONSUME:
                return Protocol\BasicConsumeFrame::unpack($buffer);
            case Constants::METHOD_BASIC_CONSUME_OK:
                return Protocol\BasicConsumeOkFrame::unpack($buffer);
            case Constants::METHOD_BASIC_CANCEL:
                return Protocol\BasicCancelFrame::unpack($buffer);
            case Constants::METHOD_BASIC_CANCEL_OK:
                return Protocol\BasicCancelOkFrame::unpack($buffer);
            case Constants::METHOD_BASIC_RECOVER:
                return Protocol\BasicRecoverFrame::unpack($buffer);
            case Constants::METHOD_BASIC_RECOVER_OK:
                return new Protocol\BasicRecoverOkFrame;
            case Constants::METHOD_BASIC_RECOVER_ASYNC:
                return Protocol\BasicRecoverAsyncFrame::unpack($buffer);
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_BASIC, $methodId);
        }
    }
    
    /**
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeConnectionFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_CONNECTION_START:
                return Protocol\ConnectionStartFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_START_OK:
                return Protocol\ConnectionStartOkFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_SECURE:
                return Protocol\ConnectionSecureFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_SECURE_OK:
                return Protocol\ConnectionSecureOkFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_TUNE:
                return Protocol\ConnectionTuneFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_TUNE_OK:
                return Protocol\ConnectionTuneOkFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_OPEN:
                return Protocol\ConnectionOpenFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_OPEN_OK:
                return Protocol\ConnectionOpenOkFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_CLOSE:
                return Protocol\ConnectionCloseFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_CLOSE_OK:
                return new Protocol\ConnectionCloseOkFrame;
            case Constants::METHOD_CONNECTION_BLOCKED:
                return Protocol\ConnectionBlockedFrame::unpack($buffer);
            case Constants::METHOD_CONNECTION_UNBLOCKED:
                return new Protocol\ConnectionUnblockedFrame;
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_CONNECTION, $methodId);
        }
    }
    
    /**
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeChannelFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_CHANNEL_OPEN:
                return Protocol\ChannelOpenFrame::unpack($buffer);
            case Constants::METHOD_CHANNEL_OPEN_OK:
                return Protocol\ChannelOpenOkFrame::unpack($buffer);
            case Constants::METHOD_CHANNEL_FLOW:
                return Protocol\ChannelFlowFrame::unpack($buffer);
            case Constants::METHOD_CHANNEL_FLOW_OK:
                return Protocol\ChannelFlowOkFrame::unpack($buffer);
            case Constants::METHOD_CHANNEL_CLOSE:
                return Protocol\ChannelCloseFrame::unpack($buffer);
            case Constants::METHOD_CHANNEL_CLOSE_OK:
                return new Protocol\ChannelCloseOkFrame;
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_CHANNEL, $methodId);
        }
    }
    
    /**
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeExchangeFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_EXCHANGE_DECLARE:
                return Protocol\ExchangeDeclareFrame::unpack($buffer);
            case Constants::METHOD_EXCHANGE_DECLARE_OK:
                return new Protocol\ExchangeDeclareOkFrame;
            case Constants::METHOD_EXCHANGE_DELETE:
                return Protocol\ExchangeDeleteFrame::unpack($buffer);
            case Constants::METHOD_EXCHANGE_DELETE_OK:
                return new Protocol\ExchangeDeleteOkFrame;
            case Constants::METHOD_EXCHANGE_BIND:
                return Protocol\ExchangeBindFrame::unpack($buffer);
            case Constants::METHOD_EXCHANGE_BIND_OK:
                return new Protocol\ExchangeBindOkFrame;
            case Constants::METHOD_EXCHANGE_UNBIND:
                return Protocol\ExchangeUnbindFrame::unpack($buffer);
            case Constants::METHOD_EXCHANGE_UNBIND_OK:
                return new Protocol\ExchangeUnbindOkFrame;
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_EXCHANGE, $methodId);
        }
    }

    /**
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeQueueFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_QUEUE_DECLARE:
                return Protocol\QueueDeclareFrame::unpack($buffer);
            case Constants::METHOD_QUEUE_DECLARE_OK:
                return Protocol\QueueDeclareOkFrame::unpack($buffer);
            case Constants::METHOD_QUEUE_BIND:
                return Protocol\QueueBindFrame::unpack($buffer);
            case Constants::METHOD_QUEUE_BIND_OK:
                return new Protocol\QueueBindOkFrame;
            case Constants::METHOD_QUEUE_UNBIND:
                return Protocol\QueueUnbindFrame::unpack($buffer);
            case Constants::METHOD_QUEUE_UNBIND_OK:
                return new Protocol\QueueUnbindOkFrame;
            case Constants::METHOD_QUEUE_PURGE:
                return Protocol\QueuePurgeFrame::unpack($buffer);
            case Constants::METHOD_QUEUE_PURGE_OK:
                return Protocol\QueuePurgeOkFrame::unpack($buffer);
            case Constants::METHOD_QUEUE_DELETE:
                return Protocol\QueueDeleteFrame::unpack($buffer);
            case Constants::METHOD_QUEUE_DELETE_OK:
                return Protocol\QueueDeleteOkFrame::unpack($buffer);
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_QUEUE, $methodId);
        }
    }
    
    /**
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeAccessFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_ACCESS_REQUEST:
                return Protocol\AccessRequestFrame::unpack($buffer);
            case Constants::METHOD_ACCESS_REQUEST_OK:
                return Protocol\AccessRequestOkFrame::unpack($buffer);
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_ACCESS, $methodId);
        }
    }
    
    /**
     * @param int $methodId
     *
     * @return Protocol\MethodFrame
     */
    private function consumeTxFrame(int $methodId): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_TX_SELECT:
                return new Protocol\TxSelectFrame;
            case Constants::METHOD_TX_SELECT_OK:
                return new Protocol\TxSelectOkFrame;
            case Constants::METHOD_TX_COMMIT:
                return new Protocol\TxCommitFrame;
            case Constants::METHOD_TX_COMMIT_OK:
                return new Protocol\TxCommitOkFrame;
            case Constants::METHOD_TX_ROLLBACK:
                return new Protocol\TxRollbackFrame;
            case Constants::METHOD_TX_ROLLBACK_OK:
                return new Protocol\TxRollbackOkFrame;
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_TX, $methodId);
        }
    }
    
    /**
     * @param int    $methodId
     * @param Buffer $buffer
     *
     * @return Protocol\MethodFrame
     */
    private function consumeConfirmFrame(int $methodId, Buffer $buffer): Protocol\MethodFrame
    {
        switch ($methodId) {
            case Constants::METHOD_CONFIRM_SELECT:
                return Protocol\ConfirmSelectFrame::unpack($buffer);
            case Constants::METHOD_CONFIRM_SELECT_OK:
                return new Protocol\ConfirmSelectOkFrame;
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_CONFIRM, $methodId);
        }
    }
}
