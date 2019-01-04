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

class BasicDeliverFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $consumerTag;

    /**
     * @var int
     */
    public $deliveryTag;

    /**
     * @var boolean
     */
    public $redelivered = false;

    /**
     * @var string
     */
    public $exchange;

    /**
     * @var string
     */
    public $routingKey;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_DELIVER);
    }
}
