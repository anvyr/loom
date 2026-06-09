<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Services;

use Anvyr\Loom\Content\Index\JsonPageIndex;
use Anvyr\Loom\Content\Index\PageIndexer;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Drivers\Content\FileDriver;
use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Services\PageService;
use Anvyr\Loom\Support\Cache\CacheTagManager;
use Anvyr\Loom\Tests\Support\Concerns\CreatesContentParser;
use Anvyr\Loom\Tests\Support\TestCase;

final class PageServiceTest extends TestCase
{
    use CreatesContentParser;

    private PageService $service;
    private EventDispatcher $events;
    private string $contentPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentPath = $this->tmpDir . '/content/pages';
        if (!is_dir($this->contentPath)) {
            mkdir($this->contentPath, 0755, true);
        }

        $this->events = new EventDispatcher();
        $cache = $this->makeFileCache('test_');
        $tagManager = new CacheTagManager($cache);

        $parser = $this->makeContentParser();
        $index = new JsonPageIndex($this->pageIndexJsonPath());
        $driver = new FileDriver(
            $parser,
            $index,
            new PageIndexer(),
            $this->contentPath,
        );

        $this->service = new PageService(
            $driver,
            $index,
            $this->events,
            $cache,
            $tagManager,
            Application::getInstance()->make(ConfigRepository::class),
        );
    }

    public function test_dispatches_events_on_load(): void
    {
        $page = new Page('event-test', 'Event Test', 'Content');
        $this->service->save($page);

        $eventsDispatched = [];

        $this->events->listen('page.loading', function ($slug) use (&$eventsDispatched) {
            $eventsDispatched[] = 'loading';
            return $slug;
        });

        $this->events->listen('page.loaded', function ($page) use (&$eventsDispatched) {
            $eventsDispatched[] = 'loaded';
            return $page;
        });

        $this->service->load('event-test');

        $this->assertContains('loading', $eventsDispatched);
        $this->assertContains('loaded', $eventsDispatched);
    }

    public function test_dispatches_events_on_save(): void
    {
        $eventsDispatched = [];

        $this->events->listen('page.saving', function ($page) use (&$eventsDispatched) {
            $eventsDispatched[] = 'saving';
            return $page;
        });

        $this->events->listen('page.saved', function ($page) use (&$eventsDispatched) {
            $eventsDispatched[] = 'saved';
            return $page;
        });

        $page = new Page('save-event', 'Save Event', 'Content');
        $this->service->save($page);

        $this->assertContains('saving', $eventsDispatched);
        $this->assertContains('saved', $eventsDispatched);
    }

    public function test_caches_loaded_pages(): void
    {
        $page = new Page('cache-test', 'Cache Test', 'Content');
        $this->service->save($page);

        // First load - should cache
        $loaded1 = $this->service->load('cache-test');

        // Second load - should hit cache
        $loaded2 = $this->service->load('cache-test');

        $this->assertSame($loaded1->title, $loaded2->title);
    }

    public function test_clears_cache_on_save(): void
    {
        $page = new Page('cache-clear', 'Cache Clear', 'Original');
        $this->service->save($page);

        // Load and cache
        $loaded1 = $this->service->load('cache-clear');
        $this->assertSame('Original', $loaded1->content);

        // Update page
        $page->content = 'Updated';
        $this->service->save($page);

        // Load again - should get updated version
        $loaded2 = $this->service->load('cache-clear');
        $this->assertSame('Updated', $loaded2->content);
    }

    public function test_can_get_published_pages(): void
    {
        $this->service->save(new Page('p1', 'Page 1', 'C', status: 'published'));
        $this->service->save(new Page('p2', 'Page 2', 'C', status: 'published'));
        $this->service->save(new Page('p3', 'Page 3', 'C', status: 'draft'));

        $published = $this->service->published();

        $this->assertSame(2, $published->count());
    }

    public function test_can_get_draft_pages(): void
    {
        $this->service->save(new Page('p1', 'Page 1', 'C', status: 'published'));
        $this->service->save(new Page('p2', 'Page 2', 'C', status: 'draft'));
        $this->service->save(new Page('p3', 'Page 3', 'C', status: 'draft'));

        $drafts = $this->service->drafts();

        $this->assertSame(2, $drafts->count());
    }

    public function test_list_cache_is_invalidated_on_save(): void
    {
        $page = new Page('list-cache', 'List Cache', 'Content', status: 'published');
        $this->service->save($page);

        $published = $this->service->published();
        $this->assertSame(1, $published->count());

        $page->status = 'draft';
        $this->service->save($page);

        $publishedAfterUpdate = $this->service->published();
        $this->assertSame(0, $publishedAfterUpdate->count());
    }

    public function test_list_cache_is_invalidated_on_delete(): void
    {
        $page = new Page('list-delete', 'List Delete', 'Content', status: 'published');
        $this->service->save($page);

        $this->service->published();

        $this->service->delete('list-delete');

        $publishedAfterDelete = $this->service->published();
        $this->assertSame(0, $publishedAfterDelete->count());
    }

    public function test_can_modify_page_via_event(): void
    {
        // Add event listener that modifies page title
        $this->events->listen('page.loaded', function ($page) {
            $page->title = 'Modified: ' . $page->title;
            return $page;
        });

        $page = new Page('modify-test', 'Original Title', 'Content');
        $this->service->save($page);

        $loaded = $this->service->load('modify-test');

        $this->assertSame('Modified: Original Title', $loaded->title);
    }
}
