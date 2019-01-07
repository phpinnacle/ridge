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

class BasicQosFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $prefetchSize = 0;

    /**
     * @var int
     */
    public $prefetchCount = 0;

    /**
     * @var boolean
     */
    public $global = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_QOS);
    }
}
