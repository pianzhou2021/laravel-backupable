<?php

namespace Pianzhou\Backupable\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Pianzhou\Backupable\ServiceProvider;
use Illuminate\Support\Env;
use Dotenv\Dotenv;

class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     *  Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    { // Load .env.testing file
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        parent::setUp();

        // 确保测试迁移被加载
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {

        // 加载外部数据库配置
        $dbConfig = require __DIR__ . '/config/database.php';
        $app['config']->set('database', $dbConfig);
    }
}
