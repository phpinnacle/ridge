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

class ConnectionSecureFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $challenge;

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_SECURE);

        $this->channel   = Constants::CONNECTION_CHANNEL;
        $this->challenge = $buffer->consumeText();
    }
}
