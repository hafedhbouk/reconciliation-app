<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase runs migrate:fresh: refuse to start unless the tests use
     * the in-memory database, so a shell exporting the .env DB_* variables can
     * never wipe the development database again.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        if ($connection !== 'sqlite' || config("database.connections.{$connection}.database") !== ':memory:') {
            throw new RuntimeException("Tests refusés : la base « {$connection} » n'est pas SQLite en mémoire.");
        }

        return parent::setUpTraits();
    }
}
