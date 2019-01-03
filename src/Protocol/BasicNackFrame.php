<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * AMQP 'basic.nack' (class #60, method #120) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class BasicNackFrame extends MethodFrame
{

    /** @var int */
    public $deliveryTag = 0;

    /** @var boolean */
    public $multiple = false;

    /** @var boolean */
    public $requeue = true;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_BASIC, Constants::METHOD_BASIC_NACK);
    }

}
