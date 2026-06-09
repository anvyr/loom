<?php

declare(strict_types=1);

namespace Anvyr\Loom\Drivers\Content;

use Anvyr\Loom\Content\Index\PageIndex;
use Anvyr\Loom\Content\Index\PageIndexEntry;
use Anvyr\Loom\Content\Index\PageIndexer;
use Anvyr\Loom\Content\Index\PageIndexQuery;
use Anvyr\Loom\Contracts\ContentDriver;
use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Exceptions\NotFoundException;
use Anvyr\Loom\Exceptions\ValidationException;
use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Services\ContentParser;

class FileDriver implements ContentDriver
{
    private string $contentPath;
    private bool $synced = false;

    public function __construct(
        private readonly ContentParser $parser,
        private readonly PageIndex $index,
        private readonly PageIndexer $pageIndexer,
        ?string $contentPath = null,
    ) {
        $this->contentPath = $contentPath ?? content_path('pages');

        if (!is_dir($this->contentPath)) {
            mkdir($this->contentPath, 0755, true);
        }
    }

    public function load(string $slug): Page
    {
        $this->ensureSynced();

        $entry = $this->index->get($slug);
        if ($entry === null) {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        return $this->hydratePage($entry);
    }

    public function save(Page $page): bool
    {
        $this->validatePage($page);

        $filepath = $this->getWritePath($page->slug);

        $content = $this->buildFileContent($page);

        $result = file_put_contents($filepath, $content) !== false;

        if ($result) {
            $mtime = filemtime($filepath) ?: time();
            $this->index->put($this->pageIndexer->indexFile($page->slug, $filepath, $mtime));
        }

        return $result;
    }

    public function list(array $filters = []): Collection
    {
        $this->ensureSynced();

        return new Collection(array_map(
            static fn (PageIndexEntry $entry): Page => $entry->toPage(),
            $this->index->query(PageIndexQuery::fromFilters($filters)),
        ));
    }

    public function paginate(int $page = 1, int $perPage = 20, array $filters = []): Collection
    {
        $filters['offset'] = ($page - 1) * $perPage;
        $filters['limit'] = $perPage;

        return $this->list($filters);
    }

    public function delete(string $slug): bool
    {
        $filepath = $this->getFilePath($slug);

        if (!file_exists($filepath)) {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        $deleted = unlink($filepath);

        if ($deleted) {
            $this->index->delete($slug);
        }

        return $deleted;
    }

    public function exists(string $slug): bool
    {
        $this->ensureSynced();

        return $this->index->get($slug) !== null;
    }

    public function count(array $filters = []): int
    {
        $this->ensureSynced();

        return $this->index->count(PageIndexQuery::fromFilters($filters));
    }

    public function lastModified(string $slug): ?int
    {
        $this->ensureSynced();

        return $this->index->get($slug)?->mtime;
    }

    /** Read the source file and parse it through ContentParser to produce a full Page. */
    private function hydratePage(PageIndexEntry $entry): Page
    {
        $raw = file_get_contents($entry->path);
        if ($raw === false) {
            throw new NotFoundException("Page file not readable: {$entry->slug}");
        }

        $parsed = $this->parser->parse($raw, $entry->format);
        $page = $entry->toPage();
        $page->content = $parsed['body'];
        $page->setHtml($parsed['html']);

        return $page;
    }

    private function getFilePath(string $slug): string
    {
        $safeSlug = sanitize_slug($slug);
        if ($safeSlug === '') {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        // Check for .vlt first, then .md
        $vltPath = $this->contentPath . '/' . $safeSlug . '.vlt';
        if (file_exists($vltPath)) {
            return $vltPath;
        }
        return $this->contentPath . '/' . $safeSlug . '.md';
    }

    /**
     * Resolve write path: existing file keeps its extension, new pages default to .vlt.
     */
    private function getWritePath(string $slug): string
    {
        $safeSlug = sanitize_slug($slug);
        if ($safeSlug === '') {
            throw new NotFoundException("Page '{$slug}' not found");
        }

        // Existing file keeps its extension
        $vltPath = $this->contentPath . '/' . $safeSlug . '.vlt';
        if (file_exists($vltPath)) {
            return $vltPath;
        }

        $mdPath = $this->contentPath . '/' . $safeSlug . '.md';
        if (file_exists($mdPath)) {
            return $mdPath;
        }

        // New pages are always .vlt
        return $vltPath;
    }

    private function buildFileContent(Page $page): string
    {
        $frontmatter = [
            'id' => $page->id,
            'title' => $page->title,
            'status' => $page->status,
        ];

        if ($page->layout) {
            $frontmatter['layout'] = $page->layout;
        }

        if ($page->trusted) {
            $frontmatter['trusted'] = true;
        }

        if ($page->excerpt) {
            $frontmatter['excerpt'] = $page->excerpt;
        }

        if ($page->createdAt) {
            $frontmatter['created_at'] = $page->createdAt->format('Y-m-d H:i:s');
        }

        if ($page->updatedAt) {
            $frontmatter['updated_at'] = $page->updatedAt->format('Y-m-d H:i:s');
        }

        if ($page->publishedAt) {
            $frontmatter['published_at'] = $page->publishedAt->format('Y-m-d H:i:s');
        }

        // Add custom meta
        foreach ($page->meta as $key => $value) {
            $frontmatter[$key] = $value;
        }

        // Use Symfony YAML dumper for proper formatting
        $yaml = "---\n";
        $yaml .= \Symfony\Component\Yaml\Yaml::dump($frontmatter, inline: 2, indent: 2);
        $yaml .= "---\n\n";

        return $yaml . $page->content;
    }

    /**
     * @return array<string, string>
     */
    private function scanFiles(): array
    {
        $filesBySlug = [];

        foreach (glob($this->contentPath . '/*.md') ?: [] as $file) {
            $filesBySlug[basename($file, '.md')] = $file;
        }

        foreach (glob($this->contentPath . '/*.vlt') ?: [] as $file) {
            $filesBySlug[basename($file, '.vlt')] = $file;
        }

        ksort($filesBySlug);

        return $filesBySlug;
    }

    private function ensureSynced(): void
    {
        if ($this->synced) {
            return;
        }

        $this->index->sync($this->scanFiles(), $this->pageIndexer);
        $this->synced = true;
    }

    private function validatePage(Page $page): void
    {
        $errors = [];

        if (empty($page->id) || !is_uuid($page->id)) {
            $errors['id'] = ['Valid UUID is required'];
        }

        if (empty($page->slug)) {
            $errors['slug'] = ['Slug is required'];
        } elseif (sanitize_slug($page->slug) !== $page->slug) {
            $errors['slug'] = ['Slug contains invalid characters'];
        }

        if (empty($page->title)) {
            $errors['title'] = ['Title is required'];
        }

        if (!in_array($page->status, ['draft', 'published'])) {
            $errors['status'] = ['Invalid status'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
