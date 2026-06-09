<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services;

use Anvyr\Loom\Content\Index\PageIndex;
use Anvyr\Loom\Contracts\CacheDriver;
use Anvyr\Loom\Contracts\ContentDriver;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Database\Collection;
use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Support\Cache\CacheTagManager;

class PageService
{
    public function __construct(
        private readonly ContentDriver $driver,
        private readonly PageIndex $index,
        private readonly EventDispatcher $events,
        private readonly CacheDriver $cache,
        private readonly CacheTagManager $cacheTags,
        private readonly ConfigRepository $config
    ) {
    }

    public function load(string $slug): Page
    {
        $mtime = $this->driver->lastModified($slug);

        if ($mtime === null || !$this->cacheEnabled()) {
            $this->events->dispatch('page.loading', $slug);
            $page = $this->driver->load($slug);
            $this->events->dispatch('page.loaded', $page);

            return $page;
        }

        return $this->cache->remember("page:{$slug}:{$mtime}", $this->cacheTtl(), function () use ($slug) {
            $this->events->dispatch('page.loading', $slug);
            $page = $this->driver->load($slug);
            $this->events->dispatch('page.loaded', $page);

            return $page;
        });
    }

    /**
     * Load a page by its stable UUIDv7 identity.
     * Resolves id → slug via the PageIndex, then delegates to load().
     */
    public function loadById(string $id): Page
    {
        $entry = $this->index->getById($id);

        if ($entry === null) {
            throw new \Anvyr\Loom\Exceptions\NotFoundException("Page with id '{$id}' not found");
        }

        return $this->load($entry->slug);
    }

    public function save(Page $page): bool
    {
        $this->events->dispatch('page.saving', $page);
        if ($page->createdAt === null) {
            $page->createdAt = new \DateTime();
        }
        $page->updatedAt = new \DateTime();

        $oldMtime = $this->driver->lastModified($page->slug);

        $result = $this->driver->save($page);

        if ($result) {
            if ($oldMtime !== null) {
                $this->cache->delete("page:{$page->slug}:{$oldMtime}");
            }
            $this->cacheTags->flush('pages:list');
            $this->events->dispatch('page.saved', $page);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $filters
     * @return Collection<Page>
     */
    public function list(array $filters = []): Collection
    {
        if (!$this->cacheEnabled()) {
            return $this->driver->list($filters);
        }

        $cacheKey = $this->makeListCacheKey($filters);

        return $this->cacheTags->remember('pages:list', $cacheKey, $this->cacheTtl(), function () use ($filters) {
            return $this->driver->list($filters);
        });
    }

    public function delete(string $slug): bool
    {
        $this->events->dispatch('page.deleting', $slug);

        $oldMtime = $this->driver->lastModified($slug);

        $result = $this->driver->delete($slug);

        if ($oldMtime !== null) {
            $this->cache->delete("page:{$slug}:{$oldMtime}");
        }
        $this->cacheTags->flush('pages:list');

        $this->events->dispatch('page.deleted', $slug);

        return $result;
    }

    public function exists(string $slug): bool
    {
        return $this->driver->exists($slug);
    }

    /** @return Collection<Page> */
    public function published(): Collection
    {
        return $this->list(['status' => 'published']);
    }

    /** @return Collection<Page> */
    public function drafts(): Collection
    {
        return $this->list(['status' => 'draft']);
    }

    /** @param array<string, mixed> $filters */
    public function count(array $filters = []): int
    {
        return $this->driver->count($filters);
    }

    /** @return Collection<Page> */
    public function recent(int $limit = 5): Collection
    {
        return $this->list([
            'order_by' => 'updated_at',
            'order' => 'desc',
            'limit' => $limit,
        ]);
    }

    private function cacheEnabled(): bool
    {
        return (bool) $this->config->get('content.drivers.file.cache_enabled', true);
    }

    private function cacheTtl(): int
    {
        return (int) $this->config->get('content.drivers.file.cache_ttl', 300);
    }

    /** @param array<string, mixed> $filters */
    private function makeListCacheKey(array $filters): string
    {
        if ($filters === []) {
            return 'pages:list:all';
        }

        ksort($filters);

        return 'pages:list:' . md5(serialize($filters));
    }
}
