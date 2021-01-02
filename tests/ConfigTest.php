<?php
/**
 * This file is part of PHPinnacle/Ridge.
 *
 * (c) PHPinnacle Team <dev@phpinnacle.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPinnacle\Ridge\Tests;

use PHPinnacle\Ridge\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testCreate(): void
    {
        $config = new Config();

        self::assertSame('localhost', $config->host);
        self::assertSame(5672, $config->port);
        self::assertSame('/', $config->vhost);
        self::assertSame('guest', $config->user);
        self::assertSame('guest', $config->pass);
    }

    public function testUri(): void
    {
        $default = new Config();
        $custom = new Config('my-domain.com', 6672);

        self::assertSame('tcp://localhost:5672', $default->uri());
        self::assertSame('tcp://my-domain.com:6672', $custom->uri());
    }

    public function testParse(): void
    {
        $config = Config::parse('amqp://user:pass@localhost:5672/test');

        self::assertSame('localhost', $config->host);
        self::assertSame(5672, $config->port);
        self::assertSame('test', $config->vhost);
        self::assertSame('user', $config->user);
        self::assertSame('pass', $config->pass);
    }

    public function testVhost(): void
    {
        self::assertSame('test', Config::parse('amqp://localhost:5672/test')->vhost);
        self::assertSame('/', Config::parse('amqp://localhost:5672/')->vhost);
        self::assertSame('/', Config::parse('amqp://localhost:5672')->vhost);
        self::assertSame('test/', Config::parse('amqp://localhost:5672/test/')->vhost);
        self::assertSame('test/test', Config::parse('amqp://localhost:5672/test/test')->vhost);
        self::assertSame('test/test//', Config::parse('amqp://localhost:5672/test/test//')->vhost);
    }
}
