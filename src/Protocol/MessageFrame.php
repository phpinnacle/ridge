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

class MessageFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $exchange;

    /**
     * @var string
     */
    public $routingKey;

    /**
     * @var int
     */
    public $deliveryTag;

    /**
     * @var boolean
     */
    public $redelivered = false;
}
