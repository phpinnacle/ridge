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
    
    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_START_OK);
    
        $this->channel = Constants::CONNECTION_CHANNEL;
    }
    
    /**
     * @param Buffer $buffer
     *
     * @return self
     */
    public static function unpack(Buffer $buffer): self
    {
        $self = new self;
        $self->clientProperties = $buffer->consumeTable();
        $self->mechanism        = $buffer->consumeString();
        $self->response         = $buffer->consumeText();
        $self->locale           = $buffer->consumeString();
        
        return $self;
    }

    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendTable($this->clientProperties);
        $buffer->appendString($this->mechanism);
        $buffer->appendText($this->response);
        $buffer->appendString($this->locale);
        
        return $buffer;
    }
}
