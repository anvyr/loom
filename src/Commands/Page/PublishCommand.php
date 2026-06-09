<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Page;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Services\PageService;

class PublishCommand extends Command
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
        return 'page:publish <slug>';
    }

    public function description(): string
    {
        return 'Publish a draft page';
    }

    public function handle(): int
    {
        $slug = $this->argument(0);

        if (!$slug) {
            $this->error('Page slug is required');
            $this->line('Usage: loom page:publish <slug>');
            return 1;
        }

        if (!$this->pageService->exists($slug)) {
            $this->error("Page '{$slug}' not found");
            return 1;
        }

        $page = $this->pageService->load($slug);

        if ($page->isPublished()) {
            $this->info("Page '{$slug}' is already published");
            return 0;
        }

        $page->publish();
        $this->pageService->save($page);

        $this->success("Page '{$slug}' published successfully!");

        return 0;
    }
}
