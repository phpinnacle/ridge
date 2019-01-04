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

use function Amp\asyncCall, Amp\call, Amp\Socket\connect;
use Amp\Deferred;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\Socket;

final class Connection
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Heartbeat
     */
    private $heartbeat;

    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @var Socket
     */
    private $socket;

    /**
     * @var Deferred[][][]
     */
    private $await = [];

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config    = $config;
        $this->heartbeat = new Heartbeat($this);
        $this->buffer    = new Buffer;
    }

    /**
     * @param Protocol\AbstractFrame $frame
     *
     * @return Promise
     */
    public function send(Protocol\AbstractFrame $frame): Promise
    {
        return $this->write(ProtocolWriter::buffer($frame));
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param Buffer $payload
     *
     * @return Promise
     */
    public function write(Buffer $payload): Promise
    {
        $this->heartbeat->touch();

        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->socket->write((string) $payload);
    }

    /**
     * @param string $frame
     * @param int    $channel
     *
     * @return Promise
     */
    public function await(string $frame, int $channel): Promise
    {
        $deferred = new Deferred;

        $this->await[$frame][$channel][] = $deferred;

        return $deferred->promise();
    }

    /**
     * @return Promise
     */
    public function connect(): Promise
    {
        return call(function () {
            $context  = (new ClientConnectContext)->withConnectTimeout($this->config->timeout());

            $this->socket = yield connect($this->config->uri(), $context);

            $buffer = new Buffer;
            $buffer
                ->append('AMQP')
                ->appendUint8(0)
                ->appendUint8(0)
                ->appendUint8(9)
                ->appendUint8(1)
            ;

            yield $this->write($buffer);

            asyncCall(function () {
                while (null !== $chunk = yield $this->socket->read()) {
                    $this->buffer->append($chunk);

                    $this->consume();
                }
            });

            return $this->doConnect();
        });
    }

    /**
     * @param int    $replyCode
     * @param string $replyText
     *
     * @return Promise<void>
     */
    public function disconnect(int $replyCode = 0, string $replyText = ''): Promise
    {
        return call(function() use ($replyCode, $replyText) {
            $this->heartbeat->disable();

            yield $this->connectionClose($replyCode, $replyText);
            yield $this->connectionCloseOk();

            // $this->closeSocket(); // TODO
        });
    }

    /**
     * @return void
     */
    private function consume(): void
    {
        while ($frame = ProtocolReader::frame($this->buffer)) {
            $class  = \get_class($frame);
            $defers = $this->await[$class][$frame->channel] ?? [];

            foreach ($defers as $defer) {
                $defer->resolve($frame);
            }

            unset($this->await[$class]);
        }
    }

    /**
     * Execute connect
     *
     * @return Promise<void>
     */
    private function doConnect(): Promise
    {
        return call(function () {
            yield $this->connectionStart();
            yield $this->connectionTune();

            return $this->connectionOpen((string) ($this->options['vhost'] ?? '/'));
        });
    }

    /**
     * @return Promise
     */
    private function connectionStart(): Promise
    {
        return call(function () {
            /** @var Protocol\ConnectionStartFrame $start */
            $start = yield $this->await(Protocol\ConnectionStartFrame::class, 0);

            if (\strpos($start->mechanisms, "AMQPLAIN") === false) {
                throw new Exception\ClientException("Server does not support AMQPLAIN mechanism (supported: {$start->mechanisms}).");
            }

            $buffer = new Buffer;
            $buffer
                ->appendTable([
                    "LOGIN"    => $this->config->user(),
                    "PASSWORD" => $this->config->password(),
                ])
                ->discard(4)
            ;

            $frameBuffer = new Buffer;
            $frameBuffer
                ->appendUint16(10)
                ->appendUint16(11)
                ->appendTable([])
                ->appendString("AMQPLAIN")
                ->appendText((string) $buffer)
                ->appendString("en_US")
            ;

            $frame = new Protocol\MethodFrame(10, 11);
            $frame->channel = 0;
            $frame->size    = $frameBuffer->size();
            $frame->payload = $frameBuffer;

            return $this->send($frame);
        });
    }

    /**
     * @return Promise
     */
    private function connectionTune(): Promise
    {
        return call(function () {
            /** @var Protocol\ConnectionTuneFrame $tune */
            $tune = yield $this->await(Protocol\ConnectionTuneFrame::class, 0);

            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16(0)
                ->appendUint32(12)
                ->appendUint16(10)
                ->appendUint16(31)
                ->appendInt16($tune->channelMax)
                ->appendInt32($tune->frameMax)
                ->appendInt16($tune->heartbeat)
                ->appendUint8(206);

            yield $this->write($buffer);

            if ($tune->heartbeat > 0) {
                $this->heartbeat->enable($tune->heartbeat);
            }
        });
    }

    /**
     * @param string $virtualHost
     * @param string $capabilities
     * @param bool   $insist
     *
     * @return Promise<Protocol\ConnectionOpenOkFrame>
     */
    private function connectionOpen(string $virtualHost = '/', string $capabilities = '', bool $insist = false): Promise
    {
        return call(function () use ($virtualHost, $capabilities, $insist) {
            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16(0)
                ->appendUint32(7 + \strlen($virtualHost) + \strlen($capabilities))
                ->appendUint16(10)
                ->appendUint16(40)
                ->appendString($virtualHost)
                ->appendString($capabilities)
                ->appendBits([$insist])
                ->appendUint8(206)
            ;

            yield $this->write($buffer);

            return $this->await(Protocol\ConnectionOpenOkFrame::class, 0);
        });
    }

    /**
     * @param int    $code
     * @param string $reason
     *
     * @return Promise
     */
    private function connectionClose(int $code, string $reason): Promise
    {
        $buffer = new Buffer;
        $buffer
            ->appendUint8(1)
            ->appendUint16(0)
            ->appendUint32(11 + \strlen($reason))
            ->appendUint16(10)
            ->appendUint16(50)
            ->appendInt16($code)
            ->appendString($reason)
            ->appendInt16(0)
            ->appendInt16(0)
            ->appendUint8(206)
        ;

        return $this->write($buffer);
    }

    /**
     * @return Promise
     */
    private function connectionCloseOk(): Promise
    {
        return call(function () {
            yield $this->await(Protocol\ConnectionCloseOkFrame::class, 0);

            $buffer = new Buffer;
            $buffer
                ->appendUint8(1)
                ->appendUint16(0)
                ->appendUint32(4)
                ->appendUint16(10)
                ->appendUint16(51)
                ->appendUint8(206)
            ;

            return $this->write($buffer);
        });
    }

//    /**
//     * @return void
//     */
//    private function closeSocket(): void
//    {
//        if ($this->socket) {
//            $this->socket->close();
//            $this->socket = null;
//        }
//    }
}
