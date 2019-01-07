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

class ConnectionOpenFrame extends MethodFrame
{
    /**
     * @var string
     */
    public $virtualHost = '/';

    /**
     * @var string
     */
    public $capabilities = '';

    /**
     * @var boolean
     */
    public $insist = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_OPEN);

        $this->channel = Constants::CONNECTION_CHANNEL;
    }
}
