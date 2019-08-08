<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PHPinnacle\Ridge\Exception;

use PHPinnacle\Ridge\Protocol;

final class ClientException extends RidgeException
{
    /**
     * @param \Throwable $error
     *
     * @return self
     */
    public static function unexpectedResponse(\Throwable $error): self
    {
        return new self("Unexpected response.", $error->getCode(), $error);
    }

    /**
     * @return self
     */
    public static function notConnected(): self
    {
        return new self("Client is not connected to server.");
    }

    /**
     * @return self
     */
    public static function alreadyConnected(): self
    {
        return new self("Client is already connected/connecting.");
    }
    
    /**
     * @param string $available
     *
     * @return self
     */
    public static function notSupported(string $available): self
    {
        return new self("Server does not support AMQPLAIN mechanism (supported: {$available}).");
    }
    
    /**
     * @return self
     */
    public static function noChannelsAvailable(): self
    {
        return new self("No available channels.");
    }

    /**
     * @param Protocol\ConnectionCloseFrame $frame
     *
     * @return self
     */
    public static function connectionClosed(Protocol\ConnectionCloseFrame $frame): self
    {
        return new self("Connection closed by server: {$frame->replyText}.", $frame->replyCode);
    }

    /**
     * @param Protocol\AbstractFrame $frame
     *
     * @return self
     */
    public static function unknownFrameClass(Protocol\AbstractFrame $frame): self
    {
        return new self("Unhandled frame '" . \get_class($frame) . "'.");
    }

    /**
     * @param Protocol\AbstractFrame $frame
     *
     * @return self
     */
    public static function unknownMethodFrame(Protocol\AbstractFrame $frame): self
    {
        return new self("Unhandled method frame '" . \get_class($frame) . "'.");
    }
}
