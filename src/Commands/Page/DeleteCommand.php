<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Page;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Services\PageService;

class DeleteCommand extends Command
{
    public static function category(): string
    {
        return 'Pages';
    }

    public function __construct(
        private readonly PageService $pageService
    ) {
    }

    public function signature(): string
    {
        return 'page:delete <slug> [--force]';
    }

    public function description(): string
    {
        return 'Delete a page';
    }

    public function handle(): int
    {
        $slug = $this->argument(0);

        if (!$slug) {
            $this->error('Page slug is required');
            $this->line('Usage: loom page:delete <slug>');
            return 1;
        }

        if (!$this->pageService->exists($slug)) {
            $this->error("Page '{$slug}' not found");
            return 1;
        }

        $page = $this->pageService->load($slug);

        $this->warning("You are about to delete: {$page->title} ({$slug})");

        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure?', false)) {
                $this->info('Deletion cancelled');
                return 0;
            }
        }

        $this->pageService->delete($slug);

        $this->success("Page '{$slug}' deleted successfully!");

        return 0;
    }
}
