<?php

namespace Tests\Feature;

use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Support\ConfigurationUrlParser;
use ReflectionMethod;
use Tests\TestCase;

// pgsql の sslmode（本番の DB は暗号化接続が必須）
class DatabaseSslModeTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setSslModeEnv(null);

        parent::tearDown();
    }

    private function setSslModeEnv(?string $value): void
    {
        unset($_ENV['DB_SSLMODE'], $_SERVER['DB_SSLMODE']);
        putenv($value === null ? 'DB_SSLMODE' : 'DB_SSLMODE=' . $value);
    }

    private function pgsqlConfig(): array
    {
        return (require config_path('database.php'))['connections']['pgsql'];
    }

    private function dsn(array $config): string
    {
        $method = new ReflectionMethod(PostgresConnector::class, 'getDsn');
        $method->setAccessible(true);

        return $method->invoke(new PostgresConnector, $config);
    }

    // 既定は prefer（ローカルの DB は暗号化なし）
    public function testSslModeDefaultsToPrefer(): void
    {
        $this->setSslModeEnv(null);

        $this->assertSame('prefer', $this->pgsqlConfig()['sslmode']);
    }

    // DB_SSLMODE で変えられ、接続の DSN に入る
    public function testSslModeComesFromEnvironment(): void
    {
        $this->setSslModeEnv('require');

        $config = $this->pgsqlConfig();
        $this->assertSame('require', $config['sslmode']);
        $this->assertStringContainsString("sslmode=require", $this->dsn(['host' => 'db.example.com', 'database' => 'app'] + $config));
    }

    // DATABASE_URL の ?sslmode= は設定より優先される。接続に使わない項目（channel_binding）は DSN に入らない
    public function testDatabaseUrlQueryOverridesSslMode(): void
    {
        $this->setSslModeEnv(null);

        $config = (new ConfigurationUrlParser)->parseConfiguration(
            ['url' => 'postgresql://user:secret@db.example.com/app?sslmode=require&channel_binding=require'] + $this->pgsqlConfig()
        );

        $this->assertSame('pgsql', $config['driver']);
        $this->assertSame('db.example.com', $config['host']);
        $this->assertSame('app', $config['database']);
        $this->assertSame('require', $config['sslmode']);

        $dsn = $this->dsn($config);
        $this->assertStringContainsString('sslmode=require', $dsn);
        $this->assertStringNotContainsString('channel_binding', $dsn);
    }
}
