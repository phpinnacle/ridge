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

class ConnectionStartOkFrame extends MethodFrame
{
    /**
     * @var array
     */
    public $clientProperties = [];

    /**
     * @var string
     */
    public $mechanism = 'PLAIN';

    /**
     * @var string
     */
    public $response;

    /**
     * @var string
     */
    public $locale = 'en_US';

    /**
     * @param Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_START_OK);

        $this->channel          = Constants::CONNECTION_CHANNEL;
        $this->clientProperties = $buffer->consumeTable();
        $this->mechanism        = $buffer->consumeString();
        $this->response         = $buffer->consumeText();
        $this->locale           = $buffer->consumeString();
    }
}
