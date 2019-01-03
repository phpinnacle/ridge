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

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Pinnacle\Transport\AmqpConstants;

class HeartbeatFrame extends AbstractFrame
{
    public function __construct()
    {
        parent::__construct(AmqpConstants::FRAME_HEARTBEAT, AmqpConstants::CONNECTION_CHANNEL, 0, "");
    }
}
