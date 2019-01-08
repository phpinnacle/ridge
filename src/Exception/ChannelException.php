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
    /**
     * @return self
     */
    public static function getInProgress(): self
    {
        return new self("Another 'basic.get' already in progress. You should use 'basic.consume' instead of multiple 'basic.get'.");
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
