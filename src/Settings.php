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

namespace PHPinnacle\Ridge;

final class Settings
{
    /**
     * @var int
     */
    private $frameMax;

    /**
     * @param int $frameMax
     */
    public function __construct(int $frameMax)
    {
        $this->frameMax = $frameMax;
    }

    /**
     * @return int
     */
    public function maxFrame(): int
    {
        return $this->frameMax;
    }
}
