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

namespace PHPinnacle\Ridge\Exception;

final class ChannelException extends RidgeException
{
    public static function notReady(int $id): self
    {
        return new self(\sprintf('Trying to open not ready channel #%d.', $id));
    }

    public static function alreadyClosed(int $id): self
    {
        return new self(\sprintf('Trying to close already closed channel #%d.', $id));
    }

    public static function notRegularFor(string $mode): self
    {
        return new self(\sprintf('Channel not in regular mode, cannot change to %s mode.', $mode));
    }

    public static function notTransactional(): self
    {
        return new self('Channel not in transactional mode.');
    }

    public static function getInProgress(): self
    {
        return new self(
            'Another `basic.get` already in progress. You should use `basic.consume` instead of multiple `basic.get`.'
        );
    }

    public static function frameOrder(): self
    {
        return new self('Consume frames order malformed.');
    }

    public static function bodyOverflow(int $remaining): self
    {
        return new self(\sprintf('Body overflow, received %d more bytes.', $remaining));
    }
}
