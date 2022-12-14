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

/**
 *
 */
final class ConnectionException extends RidgeException
{
    public static function writeFailed(\Throwable $previous): self
    {
        return new self(
            \sprintf('Error writing to socket: %s', $previous->getMessage()),
            (int)$previous->getCode(),
            $previous
        );
    }

    public static function socketClosed(): self
    {
        return new self('Attempting to write to a closed socket');
    }

    public static function lostConnection(): self
    {
        return new self('Socket was closed unexpectedly');
    }
}
