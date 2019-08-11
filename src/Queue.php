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

namespace PHPinnacle\Ridge;

final class Queue
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $messages;

    /**
     * @var int
     */
    private $consumers;

    /**
     * @param string $name
     * @param int    $messages
     * @param int    $consumers
     */
    public function __construct(string $name, int $messages, int $consumers)
    {
        $this->name      = $name;
        $this->messages  = $messages;
        $this->consumers = $consumers;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function messages(): int
    {
        return $this->messages;
    }

    /**
     * @return int
     */
    public function consumers(): int
    {
        return $this->consumers;
    }
}
