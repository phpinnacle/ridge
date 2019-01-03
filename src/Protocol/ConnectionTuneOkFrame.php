<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * AMQP 'connection.tune-ok' (class #10, method #31) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ConnectionTuneOkFrame extends MethodFrame
{

    /** @var int */
    public $channelMax = 0;

    /** @var int */
    public $frameMax = 0;

    /** @var int */
    public $heartbeat = 0;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONNECTION, Constants::METHOD_CONNECTION_TUNE_OK);
        $this->channel = Constants::CONNECTION_CHANNEL;
    }

}
