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
    public function frame(): ?Protocol\AbstractFrame
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

        $this->buffer->consume(7);

        $payload  = $this->buffer->consume($size);
        $frameEnd = $this->buffer->consumeUint8();

        if ($frameEnd !== Constants::FRAME_END) {
            throw Exception\ProtocolException::invalidFrameEnd($frameEnd);
        }

        switch ($type) {
            case Constants::FRAME_HEADER:
                $frame = Protocol\ContentHeaderFrame::buffer(new Buffer($payload));

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
                return new Protocol\BasicDeliverFrame($buffer);
            case Constants::METHOD_BASIC_GET:
                return new Protocol\BasicGetFrame($buffer);
            case Constants::METHOD_BASIC_GET_OK:
                return new Protocol\BasicGetOkFrame($buffer);
            case Constants::METHOD_BASIC_QOS:
                return new Protocol\BasicQosFrame($buffer);
            case Constants::METHOD_BASIC_QOS_OK:
                return new Protocol\BasicQosOkFrame;
            case Constants::METHOD_BASIC_CONSUME:
                return new Protocol\BasicConsumeFrame($buffer);
            case Constants::METHOD_BASIC_CONSUME_OK:
                return new Protocol\BasicConsumeOkFrame($buffer);
            case Constants::METHOD_BASIC_CANCEL:
                return new Protocol\BasicCancelFrame($buffer);
            case Constants::METHOD_BASIC_CANCEL_OK:
                return new Protocol\BasicCancelOkFrame($buffer);
            case Constants::METHOD_BASIC_RECOVER:
                return new Protocol\BasicRecoverFrame($buffer);
            case Constants::METHOD_BASIC_RECOVER_OK:
                return new Protocol\BasicRecoverOkFrame;
            case Constants::METHOD_BASIC_RECOVER_ASYNC:
                return new Protocol\BasicRecoverAsyncFrame($buffer);
            case Constants::METHOD_BASIC_PUBLISH:
                return new Protocol\BasicPublishFrame($buffer);
            case Constants::METHOD_BASIC_RETURN:
                return new Protocol\BasicReturnFrame($buffer);
            case Constants::METHOD_BASIC_GET_EMPTY:
                return new Protocol\BasicGetEmptyFrame($buffer);
            case Constants::METHOD_BASIC_ACK:
                return new Protocol\BasicAckFrame($buffer);
            case Constants::METHOD_BASIC_NACK:
                return new Protocol\BasicNackFrame($buffer);
            case Constants::METHOD_BASIC_REJECT:
                return new Protocol\BasicRejectFrame($buffer);
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
                return new Protocol\ConnectionStartFrame($buffer);
            case Constants::METHOD_CONNECTION_START_OK:
                return new Protocol\ConnectionStartOkFrame($buffer);
            case Constants::METHOD_CONNECTION_SECURE:
                return new Protocol\ConnectionSecureFrame($buffer);
            case Constants::METHOD_CONNECTION_SECURE_OK:
                return new Protocol\ConnectionSecureOkFrame($buffer);
            case Constants::METHOD_CONNECTION_TUNE:
                return new Protocol\ConnectionTuneFrame($buffer);
            case Constants::METHOD_CONNECTION_TUNE_OK:
                return new Protocol\ConnectionTuneOkFrame($buffer);
            case Constants::METHOD_CONNECTION_OPEN:
                return new Protocol\ConnectionOpenFrame($buffer);
            case Constants::METHOD_CONNECTION_OPEN_OK:
                return new Protocol\ConnectionOpenOkFrame($buffer);
            case Constants::METHOD_CONNECTION_CLOSE:
                return new Protocol\ConnectionCloseFrame($buffer);
            case Constants::METHOD_CONNECTION_CLOSE_OK:
                return new Protocol\ConnectionCloseOkFrame;
            case Constants::METHOD_CONNECTION_BLOCKED:
                return new Protocol\ConnectionBlockedFrame($buffer);
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
                return new Protocol\ChannelOpenFrame($buffer);
            case Constants::METHOD_CHANNEL_OPEN_OK:
                return new Protocol\ChannelOpenOkFrame($buffer);
            case Constants::METHOD_CHANNEL_FLOW:
                return new Protocol\ChannelFlowFrame($buffer);
            case Constants::METHOD_CHANNEL_FLOW_OK:
                return new Protocol\ChannelFlowOkFrame($buffer);
            case Constants::METHOD_CHANNEL_CLOSE:
                return new Protocol\ChannelCloseFrame($buffer);
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
                return new Protocol\ExchangeDeclareFrame($buffer);
            case Constants::METHOD_EXCHANGE_DECLARE_OK:
                return new Protocol\ExchangeDeclareOkFrame;
            case Constants::METHOD_EXCHANGE_DELETE:
                return new Protocol\ExchangeDeleteFrame($buffer);
            case Constants::METHOD_EXCHANGE_DELETE_OK:
                return new Protocol\ExchangeDeleteOkFrame;
            case Constants::METHOD_EXCHANGE_BIND:
                return new Protocol\ExchangeBindFrame($buffer);
            case Constants::METHOD_EXCHANGE_BIND_OK:
                return new Protocol\ExchangeBindOkFrame;
            case Constants::METHOD_EXCHANGE_UNBIND:
                return new Protocol\ExchangeUnbindFrame($buffer);
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
                return new Protocol\QueueDeclareFrame($buffer);
            case Constants::METHOD_QUEUE_DECLARE_OK:
                return new Protocol\QueueDeclareOkFrame($buffer);
            case Constants::METHOD_QUEUE_BIND:
                return new Protocol\QueueBindFrame($buffer);
            case Constants::METHOD_QUEUE_BIND_OK:
                return new Protocol\QueueBindOkFrame;
            case Constants::METHOD_QUEUE_UNBIND:
                return new Protocol\QueueUnbindFrame($buffer);
            case Constants::METHOD_QUEUE_UNBIND_OK:
                return new Protocol\QueueUnbindOkFrame;
            case Constants::METHOD_QUEUE_PURGE:
                return new Protocol\QueuePurgeFrame($buffer);
            case Constants::METHOD_QUEUE_PURGE_OK:
                return new Protocol\QueuePurgeOkFrame($buffer);
            case Constants::METHOD_QUEUE_DELETE:
                return new Protocol\QueueDeleteFrame($buffer);
            case Constants::METHOD_QUEUE_DELETE_OK:
                return new Protocol\QueueDeleteOkFrame($buffer);
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
                return new Protocol\AccessRequestFrame($buffer);
            case Constants::METHOD_ACCESS_REQUEST_OK:
                return new Protocol\AccessRequestOkFrame($buffer);
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
                return new Protocol\ConfirmSelectFrame($buffer);
            case Constants::METHOD_CONFIRM_SELECT_OK:
                return new Protocol\ConfirmSelectOkFrame;
            default:
                throw new Exception\MethodInvalid(Constants::CLASS_CONFIRM, $methodId);
        }
    }
}
