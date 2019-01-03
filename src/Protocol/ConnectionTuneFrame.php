<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * AMQP 'connection.tune' (class #10, method #30) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ConnectionTuneFrame extends MethodFrame
{

    /** @var int */
    public $channelMax = 0;

    /** @var int */
    public $frameMax = 0;

    /** @var int */
    public $heartbeat = 0;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_TUNE);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
