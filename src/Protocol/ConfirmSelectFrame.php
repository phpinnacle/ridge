<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * AMQP 'confirm.select' (class #85, method #10) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ConfirmSelectFrame extends MethodFrame
{

    /** @var boolean */
    public $nowait = false;

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONFIRM, Constants::METHOD_CONFIRM_SELECT);
    }

}
