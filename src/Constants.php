<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace PHPinnacle\Ridge;

class Constants
{
    const CONNECTION_CHANNEL = 0;

    const
        FRAME_METHOD    = 1,
        FRAME_HEADER    = 2,
        FRAME_BODY      = 3,
        FRAME_HEARTBEAT = 8,
        FRAME_MIN_SIZE  = 4096,
        FRAME_END       = 0xCE
    ;

    const
        STATUS_REPLY_SUCCESS       = 200,
        STATUS_CONTENT_TOO_LARGE   = 311,
        STATUS_NO_CONSUMERS        = 313,
        STATUS_CONNECTION_FORCED   = 320,
        STATUS_INVALID_PATH        = 402,
        STATUS_ACCESS_REFUSED      = 403,
        STATUS_NOT_FOUND           = 404,
        STATUS_RESOURCE_LOCKED     = 405,
        STATUS_PRECONDITION_FAILED = 406,
        STATUS_FRAME_ERROR         = 501,
        STATUS_SYNTAX_ERROR        = 502,
        STATUS_COMMAND_INVALID     = 503,
        STATUS_CHANNEL_ERROR       = 504,
        STATUS_UNEXPECTED_FRAME    = 505,
        STATUS_RESOURCE_ERROR      = 506,
        STATUS_NOT_ALLOWED         = 530,
        STATUS_NOT_IMPLEMENTED     = 540,
        STATUS_INTERNAL_ERROR      = 541
    ;

    const
        CLASS_CONNECTION = 10,
        CLASS_CHANNEL    = 20,
        CLASS_ACCESS     = 30,
        CLASS_EXCHANGE   = 40,
        CLASS_QUEUE      = 50,
        CLASS_BASIC      = 60,
        CLASS_TX         = 90,
        CLASS_CONFIRM    = 85 // RabbitMQ extension
    ;

    const
        METHOD_CONNECTION_START     = 10,
        METHOD_CONNECTION_START_OK  = 11,
        METHOD_CONNECTION_SECURE    = 20,
        METHOD_CONNECTION_SECURE_OK = 21,
        METHOD_CONNECTION_TUNE      = 30,
        METHOD_CONNECTION_TUNE_OK   = 31,
        METHOD_CONNECTION_OPEN      = 40,
        METHOD_CONNECTION_OPEN_OK   = 41,
        METHOD_CONNECTION_CLOSE     = 50,
        METHOD_CONNECTION_CLOSE_OK  = 51,
        METHOD_CONNECTION_BLOCKED   = 60, // RabbitMQ extension
        METHOD_CONNECTION_UNBLOCKED = 61  // RabbitMQ extension
    ;

    const
        METHOD_CHANNEL_OPEN     = 10,
        METHOD_CHANNEL_OPEN_OK  = 11,
        METHOD_CHANNEL_FLOW     = 20,
        METHOD_CHANNEL_FLOW_OK  = 21,
        METHOD_CHANNEL_CLOSE    = 40,
        METHOD_CHANNEL_CLOSE_OK = 41
    ;

    const
        METHOD_ACCESS_REQUEST    = 10,
        METHOD_ACCESS_REQUEST_OK = 11
    ;

    const
        METHOD_EXCHANGE_DECLARE    = 10,
        METHOD_EXCHANGE_DECLARE_OK = 11,
        METHOD_EXCHANGE_DELETE     = 20,
        METHOD_EXCHANGE_DELETE_OK  = 21,
        METHOD_EXCHANGE_BIND       = 30, // RabbitMQ extension
        METHOD_EXCHANGE_BIND_OK    = 31, // RabbitMQ extension
        METHOD_EXCHANGE_UNBIND     = 40, // RabbitMQ extension
        METHOD_EXCHANGE_UNBIND_OK  = 51  // RabbitMQ extension
    ;

    const
        METHOD_QUEUE_DECLARE    = 10,
        METHOD_QUEUE_DECLARE_OK = 11,
        METHOD_QUEUE_BIND       = 20,
        METHOD_QUEUE_BIND_OK    = 21,
        METHOD_QUEUE_PURGE      = 30,
        METHOD_QUEUE_PURGE_OK   = 31,
        METHOD_QUEUE_DELETE     = 40,
        METHOD_QUEUE_DELETE_OK  = 41,
        METHOD_QUEUE_UNBIND     = 50,
        METHOD_QUEUE_UNBIND_OK  = 51
    ;

    const
        METHOD_BASIC_QOS           = 10,
        METHOD_BASIC_QOS_OK        = 11,
        METHOD_BASIC_CONSUME       = 20,
        METHOD_BASIC_CONSUME_OK    = 21,
        METHOD_BASIC_CANCEL        = 30,
        METHOD_BASIC_CANCEL_OK     = 31,
        METHOD_BASIC_PUBLISH       = 40,
        METHOD_BASIC_RETURN        = 50,
        METHOD_BASIC_DELIVER       = 60,
        METHOD_BASIC_GET           = 70,
        METHOD_BASIC_GET_OK        = 71,
        METHOD_BASIC_GET_EMPTY     = 72,
        METHOD_BASIC_ACK           = 80,
        METHOD_BASIC_REJECT        = 90,
        METHOD_BASIC_RECOVER_ASYNC = 100,
        METHOD_BASIC_RECOVER       = 110,
        METHOD_BASIC_RECOVER_OK    = 111,
        METHOD_BASIC_NACK          = 120 // RabbitMQ extension
    ;

    const
        METHOD_TX_SELECT      = 10,
        METHOD_TX_SELECT_OK   = 11,
        METHOD_TX_COMMIT      = 20,
        METHOD_TX_COMMIT_OK   = 21,
        METHOD_TX_ROLLBACK    = 30,
        METHOD_TX_ROLLBACK_OK = 31
    ;

    const
        METHOD_CONFIRM_SELECT    = 10, // RabbitMQ extension
        METHOD_CONFIRM_SELECT_OK = 11  // RabbitMQ extension
    ;

    const
        FIELD_BOOLEAN          = 0x74, // 't'
        FIELD_SHORT_SHORT_INT  = 0x62, // 'b'
        FIELD_SHORT_SHORT_UINT = 0x42, // 'B'
        FIELD_SHORT_INT        = 0x55, // 'U'
        FIELD_SHORT_UINT       = 0x75, // 'u'
        FIELD_LONG_INT         = 0x49, // 'I'
        FIELD_LONG_UINT        = 0x69, // 'i'
        FIELD_LONG_LONG_INT    = 0x4C, // 'L'
        FIELD_LONG_LONG_UINT   = 0x6C, // 'l'
        FIELD_FLOAT            = 0x66, // 'f'
        FIELD_DOUBLE           = 0x64, // 'd'
        FIELD_DECIMAL_VALUE    = 0x44, // 'D'
        FIELD_SHORT_STRING     = 0x73, // 's'
        FIELD_LONG_STRING      = 0x53, // 'S'
        FIELD_ARRAY            = 0x41, // 'A'
        FIELD_TIMESTAMP        = 0x54, // 'T'
        FIELD_TABLE            = 0x46, // 'F'
        FIELD_NULL             = 0x56  // 'V'
    ;
}
