<?php

declare(strict_types=1);

namespace Yard\PostWriter\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use WP_Mock;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }
}
