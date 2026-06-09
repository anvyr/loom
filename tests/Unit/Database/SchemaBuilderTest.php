<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Database;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\Schema;
use Anvyr\Loom\Tests\Support\Concerns\CreatesTestDatabase;
use Anvyr\Loom\Tests\Support\TestCase;

final class SchemaBuilderTest extends TestCase
{
    use CreatesTestDatabase;

    private Connection $connection;
    private Schema $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->makeSqliteConnection('schema-test');
        $this->schema = new Schema($this->connection);
    }

    // === Blueprint Tests ===

    public function test_blueprint_id_creates_auto_increment_primary(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->id();

        $columns = $blueprint->getColumns();
        $this->assertCount(1, $columns);
        $this->assertSame('id', $columns[0]['name']);
        $this->assertSame('bigInteger', $columns[0]['type']);
        $this->assertTrue($columns[0]['autoIncrement']);
    }

    public function test_blueprint_string_column(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->string('name', 100);

        $columns = $blueprint->getColumns();
        $this->assertSame('name', $columns[0]['name']);
        $this->assertSame('string', $columns[0]['type']);
        $this->assertSame(100, $columns[0]['length']);
    }

    public function test_blueprint_text_column(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->text('content');

        $columns = $blueprint->getColumns();
        $this->assertSame('text', $columns[0]['type']);
    }

    public function test_blueprint_integer_column(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->integer('count');

        $columns = $blueprint->getColumns();
        $this->assertSame('integer', $columns[0]['type']);
    }

    public function test_blueprint_big_integer_column(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->bigInteger('big_count', true, true);

        $columns = $blueprint->getColumns();
        $this->assertSame('bigInteger', $columns[0]['type']);
        $this->assertTrue($columns[0]['autoIncrement']);
        $this->assertTrue($columns[0]['unsigned']);
    }

    public function test_blueprint_boolean_column(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->boolean('is_active');

        $columns = $blueprint->getColumns();
        $this->assertSame('boolean', $columns[0]['type']);
    }

    public function test_blueprint_timestamps(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->timestamps();

        $columns = $blueprint->getColumns();
        $this->assertCount(2, $columns);
        $this->assertSame('created_at', $columns[0]['name']);
        $this->assertSame('updated_at', $columns[1]['name']);
    }

    public function test_blueprint_nullable_modifier(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->string('optional')->nullable();

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]['nullable']);
    }

    public function test_blueprint_default_modifier(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->string('status')->default('pending');

        $columns = $blueprint->getColumns();
        $this->assertSame('pending', $columns[0]['default']);
    }

    public function test_blueprint_unsigned_modifier(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->integer('positive')->unsigned();

        $columns = $blueprint->getColumns();
        $this->assertTrue($columns[0]['unsigned']);
    }

    public function test_blueprint_index(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->string('email')->index();

        $commands = $blueprint->getCommands();
        $this->assertCount(1, $commands);
        $this->assertSame('index', $commands[0]['type']);
    }

    public function test_blueprint_unique(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->string('email')->unique();

        $commands = $blueprint->getCommands();
        $this->assertSame('unique', $commands[0]['type']);
    }

    public function test_blueprint_primary(): void
    {
        $blueprint = new Blueprint('test');
        $blueprint->string('code')->primary();

        $commands = $blueprint->getCommands();
        $this->assertSame('primary', $commands[0]['type']);
    }

    public function test_blueprint_foreign_key(): void
    {
        $blueprint = new Blueprint('posts');
        $foreign = $blueprint->foreign('user_id')
            ->references('id')
            ->on('users')
            ->onDelete('CASCADE');

        $commands = $blueprint->getCommands();
        $this->assertCount(1, $commands);
        $this->assertSame(['user_id'], $foreign->columns);
        $this->assertSame('users', $foreign->onTable);
        $this->assertSame(['id'], $foreign->references);
        $this->assertSame('CASCADE', $foreign->onDelete);
    }

    public function test_blueprint_create_and_drop_flags(): void
    {
        $blueprint = new Blueprint('test');
        $this->assertFalse($blueprint->creating());
        $this->assertFalse($blueprint->dropping());

        $blueprint->create();
        $this->assertTrue($blueprint->creating());

        $blueprint2 = new Blueprint('test2');
        $blueprint2->drop();
        $this->assertTrue($blueprint2->dropping());
    }

    // === Schema Create/Drop Tests ===

    public function test_schema_create_table(): void
    {
        $this->schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        $this->assertTrue($this->connection->tableExists('articles'));
    }

    public function test_schema_drop_table(): void
    {
        $this->schema->create('to_drop', function (Blueprint $table) {
            $table->id();
        });

        $this->assertTrue($this->connection->tableExists('to_drop'));

        $this->schema->drop('to_drop');

        $this->assertFalse($this->connection->tableExists('to_drop'));
    }

    public function test_schema_drop_if_exists(): void
    {
        $this->schema->dropIfExists('nonexistent_table');
        $this->assertFalse($this->connection->tableExists('nonexistent_table'));

        $this->schema->create('exists_to_drop', function (Blueprint $table) {
            $table->id();
        });

        $this->schema->dropIfExists('exists_to_drop');
        $this->assertFalse($this->connection->tableExists('exists_to_drop'));
    }

    public function test_schema_creates_table_with_indexes(): void
    {
        $this->schema->create('indexed_table', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('slug')->index();
        });

        $this->assertTrue($this->connection->tableExists('indexed_table'));
    }

    public function test_schema_creates_table_with_foreign_key(): void
    {
        $this->schema->create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $this->schema->create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->bigInteger('author_id');
            $table->foreign('author_id')->references('id')->on('authors');
        });

        $this->assertTrue($this->connection->tableExists('books'));
    }

    public function test_schema_with_nullable_and_defaults(): void
    {
        $this->schema->create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_system')->default(false);
        });

        $this->assertTrue($this->connection->tableExists('settings'));

        // Insert a row to verify defaults work
        $this->connection->table('settings')->insert([
            'key' => 'test_key',
        ]);

        $row = $this->connection->table('settings')->where('key', '=', 'test_key')->first();
        $this->assertNull($row['value']);
        $this->assertEquals(0, $row['is_system']); // SQLite stores false as 0
    }
}
