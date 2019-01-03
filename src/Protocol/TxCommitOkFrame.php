<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * AMQP 'tx.commit-ok' (class #90, method #21) frame.
 *
 * THIS CLASS IS GENERATED FROM amqp-rabbitmq-0.9.1.json. **DO NOT EDIT!**
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class TxCommitOkFrame extends MethodFrame
{

    public function __construct()
    {
        parent::__construct(Constants::CLASS_TX, Constants::METHOD_TX_COMMIT_OK);
    }

}
