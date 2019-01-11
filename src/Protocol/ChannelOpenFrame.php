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

use PHPinnacle\Ridge\Buffer;
use PHPinnacle\Ridge\Constants;

class ChannelOpenFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $outOfBand = '';
    
    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_CHANNEL, Constants::METHOD_CHANNEL_OPEN);

        $this->outOfBand = $buffer->consumeString();
    }
}
