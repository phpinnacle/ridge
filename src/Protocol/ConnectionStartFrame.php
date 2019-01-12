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

class ConnectionStartFrame extends MethodFrame
{
    /**
     * @var int
     */
    public $versionMajor = 0;

    /**
     * @var int
     */
    public $versionMinor = 9;

    /**
     * @var array
     */
    public $serverProperties = [];

    /**
     * @var string
     */
    public $mechanisms = 'PLAIN';

    /**
     * @var string
     */
    public $locales = 'en_US';
    
    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_START);
    
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
        $self->versionMajor     = $buffer->consumeUint8();
        $self->versionMinor     = $buffer->consumeUint8();
        $self->serverProperties = $buffer->consumeTable();
        $self->mechanisms       = $buffer->consumeText();
        $self->locales          = $buffer->consumeText();
        
        return $self;
    }

    /**
     * @return Buffer
     */
    public function pack(): Buffer
    {
        $buffer = parent::pack();
        $buffer->appendUint8($this->versionMajor);
        $buffer->appendUint8($this->versionMinor);
        $buffer->appendTable($this->serverProperties);
        $buffer->appendText($this->mechanisms);
        $buffer->appendText($this->locales);
        
        return $buffer;
    }
}
