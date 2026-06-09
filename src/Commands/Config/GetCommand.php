<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Config;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Core\ConfigRepository;

class GetCommand extends Command
{
    public function __construct(
        private readonly ConfigRepository $config
    ) {
    }

    public static function category(): string
    {
        return 'Config';
    }

    public function signature(): string
    {
        return 'config:get {key}';
    }

    public function description(): string
    {
        return 'Get a configuration value';
    }

    public function handle(): int
    {
        $key = $this->argument(0);
        if (!$key) {
            $this->info('Usage: loom config:get <key>');
            return 1;
        }

        $value = $this->config->get($key);

        if ($value === null) {
            echo "null\n";
        } elseif (is_bool($value)) {
            echo $value ? "true\n" : "false\n";
        } elseif (is_array($value)) {
            print_r($value);
        } else {
            echo "{$value}\n";
        }

        return 0;
    }
}
