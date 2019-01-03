<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * AMQP 'confirm.select-ok' (class #85, method #11) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class ConfirmSelectOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_CONFIRM, Constants::METHOD_CONFIRM_SELECT_OK);
    }

}
