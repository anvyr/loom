<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

class ServeCommand extends Command
{
    public static function category(): string
    {
        return 'Development';
    }

    public function signature(): string
    {
        return 'serve [--port=8000] [--host=localhost]';
    }

    public function description(): string
    {
        return 'Start PHP development server';
    }

    public function handle(): int
    {
        $host = $this->option('host', 'localhost');
        $port = $this->option('port', '8000');
        $docroot = base_path('public');

        $this->info('Starting Anvyr Loom development server...');
        $this->line();
        $this->success("Server running at: \033[36mhttp://{$host}:{$port}\033[0m");
        $this->line("Document root: {$docroot}");
        $this->line();
        $this->warning('Press Ctrl+C to stop');
        $this->line();

        $command = sprintf(
            'php -S %s:%s -t %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($docroot)
        );

        passthru($command, $exitCode);

        return $exitCode;
    }
}
