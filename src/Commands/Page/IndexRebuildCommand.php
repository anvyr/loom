<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands\Page;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Content\Index\PageIndex;
use Anvyr\Loom\Content\Index\PageIndexer;

class IndexRebuildCommand extends Command
{
    public static function category(): string
    {
        return 'Pages';
    }

    public function __construct(
        private readonly PageIndex $index,
        private readonly PageIndexer $indexer,
    ) {
    }

    public function signature(): string
    {
        return 'index:rebuild';
    }

    public function description(): string
    {
        return 'Rebuild the page index from content files';
    }

    public function handle(): int
    {
        $contentPath = (string) config('content.drivers.file.path', content_path('pages'));

        if (!is_dir($contentPath)) {
            $this->error("Content directory not found: {$contentPath}");
            return self::FAILURE;
        }

        $this->info('Scanning content files...');

        $filesBySlug = [];
        foreach (glob($contentPath . '/*.md') ?: [] as $file) {
            $filesBySlug[basename($file, '.md')] = $file;
        }
        foreach (glob($contentPath . '/*.vlt') ?: [] as $file) {
            $filesBySlug[basename($file, '.vlt')] = $file;
        }
        ksort($filesBySlug);

        $count = count($filesBySlug);
        $this->info("Found {$count} content file(s). Rebuilding index...");

        $this->index->rebuild($filesBySlug, $this->indexer);

        $this->success("Page index rebuilt ({$count} pages indexed).");

        return self::SUCCESS;
    }
}
