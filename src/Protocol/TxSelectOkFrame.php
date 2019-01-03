<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * AMQP 'tx.select-ok' (class #90, method #11) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class TxSelectOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_TX, Constants::METHOD_TX_SELECT_OK);
    }

}
