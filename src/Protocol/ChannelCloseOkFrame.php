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

class ChannelCloseOkFrame extends MethodFrame
{
    public function __construct()
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_CLOSE_OK);
    }
}
