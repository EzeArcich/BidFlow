<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $database = $app['config']->get('database.connections.pgsql.database');
        if ($app['config']->get('database.default') !== 'pgsql' || ! is_string($database) || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException('Tests require a dedicated PostgreSQL database ending in _testing.');
        }

        return $app;
    }
}
