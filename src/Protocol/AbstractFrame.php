<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PHPinnacle\Ridge\Protocol;

/**
 * Base class for all AMQP protocol frames.
 *
 * Frame classes' sole purpose is to be crate for transferring data. All fields are public because of calls to getters
 * and setters are ridiculously slow.
 *
 * You should not mangle with frame's fields, everything should be handled by classes in {@namespace \Bunny\Protocol}.
 *
 * Frame's wire format:
 *
 *     0      1         3              7               size+7     size+8
 *     +------+---------+--------------+-----------------+-----------+
 *     | type | channel |     size     | ... payload ... | frame-end |
 *     +------+---------+--------------+-----------------+-----------+
 *      uint8    uint16      uint32        size octets       uint8
 *
 * @author Jakub Kulhan <jakub.kulhan@gmail.com>
 */
abstract class AbstractFrame
{
    /**
     * @var int
     */
    public $type;

    /**
     * @var int
     */
    public $channel;

    /**
     * @var int
     */
    public $size;

    /**
     * @var string
     */
    public $payload;

    /**
     * @param int    $type
     * @param int    $channel
     * @param int    $size
     * @param string $payload
     */
    public function __construct(int $type = null, int $channel = null, int $size = null, string $payload = null)
    {
        $this->type    = $type;
        $this->channel = $channel;
        $this->size    = $size;
        $this->payload = $payload;
    }
}