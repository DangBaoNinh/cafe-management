<?php

namespace Tests\Integration\MySql;

use Dotenv\Dotenv;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class MySqlTestCase extends TestCase
{
    protected Connection $primary;

    protected Connection $peer;

    protected function setUp(): void
    {
        parent::setUp();

        $file = Dotenv::createArrayBacked(base_path(), '.env.mysql-testing')->safeLoad();
        $database = env('MYSQL_TEST_DATABASE', $file['MYSQL_TEST_DATABASE'] ?? null);
        $username = env('MYSQL_TEST_USERNAME', $file['MYSQL_TEST_USERNAME'] ?? null);
        $password = env('MYSQL_TEST_PASSWORD', $file['MYSQL_TEST_PASSWORD'] ?? null);

        $this->assertSame('coffee_shop_test', $database, 'MySQL tests require the dedicated coffee_shop_test database.');
        $this->assertNotEmpty($username, 'Configure MYSQL_TEST_USERNAME in .env.mysql-testing or the environment.');
        $this->assertNotSame('root', strtolower($username), 'Use a dedicated test account instead of root.');
        $this->assertNotEmpty($password, 'Configure MYSQL_TEST_PASSWORD in .env.mysql-testing or the environment.');

        $connection = array_replace(config('database.connections.mysql'), [
            'url' => null,
            'host' => env('MYSQL_TEST_HOST', $file['MYSQL_TEST_HOST'] ?? '127.0.0.1'),
            'port' => env('MYSQL_TEST_PORT', $file['MYSQL_TEST_PORT'] ?? 3306),
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'unix_socket' => '',
            'options' => [],
        ]);
        config([
            'database.connections.mysql-testing' => $connection,
            'database.connections.mysql-testing-peer' => $connection,
        ]);

        $this->primary = DB::connection('mysql-testing');
        $this->peer = DB::connection('mysql-testing-peer');

        foreach ([$this->primary, $this->peer] as $session) {
            $this->assertSame('coffee_shop_test', $session->selectOne('SELECT DATABASE() AS schema_name')->schema_name);
        }

        $this->artisan('migrate', ['--database' => 'mysql-testing', '--force' => true])->assertSuccessful();
    }
}
