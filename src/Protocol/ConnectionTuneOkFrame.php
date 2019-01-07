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

class ConnectionTuneOkFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $channelMax = 0;

    /**
     * @var int
     */
    public $frameMax = 0;

    /**
     * @var int
     */
    public $heartbeat = 0;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_TUNE_OK);

        $this->channel = Constants::CONNECTION_CHANNEL;
    }
}
