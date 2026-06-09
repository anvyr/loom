<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Migrations\MigrationRepository;
use Anvyr\Loom\Database\Migrations\Migrator;
use Anvyr\Loom\Database\Schema\Schema;
use Anvyr\Loom\Tests\Support\Concerns\CreatesTestDatabase;
use Anvyr\Loom\Tests\Support\TestCase;

final class MigratorTest extends TestCase
{
    use CreatesTestDatabase;

    private Connection $connection;
    private Schema $schema;
    private MigrationRepository $repository;
    private Migrator $migrator;
    private string $migrationsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrationsPath = $this->tmpDir . '/migrations';
        mkdir($this->migrationsPath, 0755, true);

        $this->connection = $this->makeSqliteConnection();
        $this->schema = new Schema($this->connection);
        $this->repository = new MigrationRepository($this->connection, $this->schema);
        $this->migrator = new Migrator($this->connection, $this->schema, $this->repository);
    }

    public function test_runs_simple_sql_migration(): void
    {
        $sql = 'CREATE TABLE test_simple (id INTEGER PRIMARY KEY, name TEXT);';
        file_put_contents($this->migrationsPath . '/001_simple.sql', $sql);

        $this->expectOutputRegex('/Migrating 001_simple \.\.\. .*✓/s');
        $this->migrator->run($this->migrationsPath);

        $this->assertTrue($this->repository->repositoryExists());
        $this->assertContains('001_simple', $this->repository->getRan());

        // Verify table exists
        $result = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_simple'");
        $this->assertCount(1, $result);
    }

    public function test_runs_complex_sql_migration_with_quotes_and_semicolons(): void
    {
        // This SQL contains semicolons inside strings, which would break a simple explode(';')
        $sql = <<<SQL
        CREATE TABLE test_complex (id INTEGER PRIMARY KEY, content TEXT);
        INSERT INTO test_complex (content) VALUES ('Hello; World');
        INSERT INTO test_complex (content) VALUES ("Another; Test");
        INSERT INTO test_complex (content) VALUES ('Escaped '' Quote');
        SQL;

        file_put_contents($this->migrationsPath . '/002_complex.sql', $sql);

        $this->expectOutputRegex('/Migrating 002_complex \.\.\. .*✓/s');
        $this->migrator->run($this->migrationsPath);

        $this->assertContains('002_complex', $this->repository->getRan());

        $rows = $this->connection->query('SELECT * FROM test_complex ORDER BY id');
        $this->assertCount(3, $rows);
        $this->assertSame('Hello; World', $rows[0]['content']);
        $this->assertSame('Another; Test', $rows[1]['content']);
        $this->assertSame("Escaped ' Quote", $rows[2]['content']);
    }

    public function test_runs_php_migration(): void
    {
        $php = <<<'PHP'
<?php

use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('test_php', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }
};
PHP;
        file_put_contents($this->migrationsPath . '/003_php.php', $php);

        $this->expectOutputRegex('/Migrating 003_php \.\.\. .*✓/s');
        $this->migrator->run($this->migrationsPath);

        $this->assertContains('003_php', $this->repository->getRan());

        $result = $this->connection->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_php'");
        $this->assertCount(1, $result);
    }

    public function test_skips_already_ran_migrations(): void
    {
        file_put_contents($this->migrationsPath . '/001_repeat.sql', 'CREATE TABLE test_repeat (id INTEGER);');

        $this->expectOutputRegex('/Migrating 001_repeat \.\.\. .*✓/s');

        // First run
        $this->migrator->run($this->migrationsPath);

        // Second run - should not fail due to "table already exists"
        $this->migrator->run($this->migrationsPath);

        $this->assertSame(['001_repeat'], $this->repository->getRan());
    }
}
