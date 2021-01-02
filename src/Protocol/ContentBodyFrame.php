<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

class ContentBodyFrame extends AbstractFrame
{
    public function __construct(?int $channel = null, ?int $payloadSize = null, ?string $payload = null)
    {
        parent::__construct(Constants::FRAME_BODY, $channel, $payloadSize, $payload);
    }
}
