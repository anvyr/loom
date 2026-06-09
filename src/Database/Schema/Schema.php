<?php

declare(strict_types=1);

namespace Anvyr\Loom\Database\Schema;

use Anvyr\Loom\Database\Connection;
use Anvyr\Loom\Database\Schema\Grammars\Grammar;
use Anvyr\Loom\Database\Schema\Grammars\MySqlGrammar;
use Anvyr\Loom\Database\Schema\Grammars\PostgresGrammar;
use Anvyr\Loom\Database\Schema\Grammars\SQLiteGrammar;

class Schema
{
    private readonly Grammar $grammar;

    public function __construct(
        private readonly Connection $connection
    ) {
        $this->grammar = self::resolveGrammar($connection);
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->create();
        $callback($blueprint);

        $this->execute($blueprint);
    }

    public function drop(string $table): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->drop();

        $this->execute($blueprint);
    }

    public function dropIfExists(string $table): void
    {
        if ($this->connection->tableExists($table)) {
            $this->drop($table);
        }
    }

    private function execute(Blueprint $blueprint): void
    {
        $statements = $this->grammar->compile($blueprint);

        foreach ($statements as $statement) {
            $this->connection->statement($statement);
        }
    }

    private static function resolveGrammar(Connection $connection): Grammar
    {
        $driver = $connection->getDriver();

        return match ($driver) {
            'mysql' => new MySqlGrammar(),
            'pgsql' => new PostgresGrammar(),
            'sqlite' => new SQLiteGrammar(),
            default => throw new \RuntimeException("Unsupported driver for Schema Builder: {$driver}"),
        };
    }
}
