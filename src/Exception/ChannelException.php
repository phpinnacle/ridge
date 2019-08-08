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

final class ChannelException extends RidgeException
{
    /**
     * @param int $id
     *
     * @return self
     */
    public static function notReady(int $id): self
    {
        return new self("Trying to open not ready channel #{$id}.");
    }
    
    /**
     * @param int $id
     *
     * @return self
     */
    public static function alreadyClosed(int $id): self
    {
        return new self("Trying to close already closed channel #{$id}.");
    }

    /**
     * @param string $mode
     *
     * @return self
     */
    public static function notRegularFor(string $mode): self
    {
        return new self("Channel not in regular mode, cannot change to {$mode} mode.");
    }
    
    /**
     * @return self
     */
    public static function notTransactional(): self
    {
        return new self("Channel not in transactional mode.");
    }

    /**
     * @return self
     */
    public static function getInProgress(): self
    {
        return new self("Another 'basic.get' already in progress. You should use 'basic.consume' instead of multiple 'basic.get'.");
    }

    /**
     * @return self
     */
    public static function frameOrder(): self
    {
        return new self("Consume frames order malformed.");
    }

    /**
     * @param int $remaining
     *
     * @return self
     */
    public static function bodyOverflow(int $remaining): self
    {
        return new self("Body overflow, received {$remaining} more bytes.");
    }
}
