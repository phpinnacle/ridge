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
    public static function unexpectedResponse(\Throwable $error): self
    {
        return new self('Unexpected response.', (int)$error->getCode(), $error);
    }

    public static function notConnected(): self
    {
        return new self('Client is not connected to server.');
    }

    public static function disconnected(): self {
        return new self('The client was unexpectedly disconnected from the server');
    }

    public static function alreadyConnected(): self
    {
        return new self('Client is already connected/connecting.');
    }

    public static function notSupported(string $available): self
    {
        return new self(\sprintf('Server does not support AMQPLAIN mechanism (supported: %s).', $available));
    }

    public static function noChannelsAvailable(): self
    {
        return new self('No available channels.');
    }

    public static function connectionClosed(Protocol\ConnectionCloseFrame $frame): self
    {
        return new self(\sprintf('Connection closed by server: %s.', $frame->replyText), $frame->replyCode);
    }

    public static function unknownFrameClass(Protocol\AbstractFrame $frame): self
    {
        return new self(\sprintf('Unhandled frame `%s`.', \get_class($frame)));
    }

    public static function unknownMethodFrame(Protocol\AbstractFrame $frame): self
    {
        return new self(\sprintf('Unhandled method frame `%s`.', \get_class($frame)));
    }
}
