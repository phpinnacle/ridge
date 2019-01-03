<?php

namespace PHPinnacle\Ridge\Protocol;

use PHPinnacle\Ridge\Constants;

/**
 * Method AMQP frame.
 *
 * Frame's payload wire format:
 *
 *
 *         0          2           4
 *     ----+----------+-----------+--------------------
 *     ... | class-id | method-id | method-arguments...
 *     ----+----------+-----------+--------------------
 *            uint16     uint16
 *
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
class MethodFrame extends AbstractFrame
{
    /**
     * @var int
     */
    public $classId;

    /**
     * @var int
     */
    public $methodId;

    /**
     * @param int $classId
     * @param int $methodId
     */
    public function __construct(int $classId = null, int $methodId = null)
    {
        parent::__construct(Constants::FRAME_METHOD);

        $this->classId = $classId;
        $this->methodId = $methodId;
    }
}
