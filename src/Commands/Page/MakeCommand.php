<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Page;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Services\PageService;

class MakeCommand extends Command
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
        return 'page:make [slug] [--title=] [--layout=] [--trusted] [--interactive]';
    }

    public function description(): string
    {
        return 'Create a new .vlt page';
    }

    public function handle(): int
    {
        if ($this->option('interactive') || !$this->arguments) {
            return $this->interactive();
        }

        $slug = $this->argument(0);

        if (!$slug) {
            $this->error('Page slug is required');
            $this->line('Usage: loom page:make <slug> [--title="Page Title"] [--layout=default] [--trusted]');
            return 1;
        }

        if ($this->pageService->exists($slug)) {
            $this->error("Page '{$slug}' already exists");
            return 1;
        }

        $title = $this->option('title', ucwords(str_replace(['-', '_'], ' ', $slug)));
        $layout = $this->option('layout', 'default');
        $trusted = (bool) $this->option('trusted');

        $page = new Page(
            slug: $slug,
            title: $title,
            content: $this->skeleton($title),
            status: 'draft',
            layout: $layout,
            trusted: $trusted
        );

        $this->pageService->save($page);

        $this->success("Page '{$slug}' created successfully!");
        $this->line("  Edit: content/pages/{$slug}.vlt");

        return 0;
    }

    private function interactive(): int
    {
        $this->line();
        $this->line("\033[1mCreate New Page\033[0m");
        $this->line();

        $slug = $this->ask('Page slug (e.g., about-us)');

        if (!$slug) {
            $this->error('Slug is required');
            return 1;
        }

        if ($this->pageService->exists($slug)) {
            $this->error("Page '{$slug}' already exists");
            return 1;
        }

        $title = $this->ask('Page title', ucwords(str_replace(['-', '_'], ' ', $slug)));
        $layout = $this->ask('Layout', 'default');
        $status = $this->choice('Status', ['draft', 'published'], '0');
        $trusted = $this->confirm('Enable trusted mode? (allows @php and {!! !!})', false);

        $page = new Page(
            slug: $slug,
            title: $title,
            content: $this->skeleton($title),
            status: $status,
            layout: $layout,
            trusted: $trusted
        );

        $this->line();
        $this->info('Creating page...');

        $this->pageService->save($page);

        $this->success("Page '{$slug}' created successfully!");
        $this->line();
        $this->line("  URL:    /{$slug}");
        $this->line("  File:   content/pages/{$slug}.vlt");
        $this->line("  Layout: {$layout}");
        if ($trusted) {
            $this->line('  Trusted: yes');
        }
        $this->line();

        return 0;
    }

    private function skeleton(string $title): string
    {
        return <<<VLT
@html
<section class="hero">
  <div class="container">
    <h1>{$title}</h1>
  </div>
</section>

@html
<section class="content">
  <div class="container">
    <p>Your content here...</p>
  </div>
</section>
VLT;
    }
}
