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

class ExchangeDeclareFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $reserved1 = 0;

    /**
     * @var string
     */
    public $exchange;

    /**
     * @var string
     */
    public $exchangeType = 'direct';

    /**
     * @var boolean
     */
    public $passive = false;

    /**
     * @var boolean
     */
    public $durable = false;

    /**
     * @var boolean
     */
    public $autoDelete = false;

    /**
     * @var boolean
     */
    public $internal = false;

    /**
     * @var boolean
     */
    public $nowait = false;

    /**
     * @var array
     */
    public $arguments = [];

    public function __construct()
    {
        parent::__construct(Constants::CLASS_EXCHANGE, Constants::METHOD_EXCHANGE_DECLARE);
    }
}
