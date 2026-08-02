<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $configCache = dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';
        if (is_file($configCache)) {
            throw new RuntimeException('Refusing to run tests while Laravel config cache is active. Run php artisan config:clear first.');
        }

        parent::setUp();

        $this->withoutVite();
    }
}
