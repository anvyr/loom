<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Content;

use Anvyr\Loom\Content\Index\JsonPageIndex;
use Anvyr\Loom\Content\Index\PageIndexer;
use Anvyr\Loom\Drivers\Content\FileDriver;
use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Tests\Support\Concerns\CreatesContentParser;
use Anvyr\Loom\Tests\Support\TestCase;

final class FileDriverTest extends TestCase
{
    use CreatesContentParser;

    private function driver(): FileDriver
    {
        $contentPath = $this->tmpDir . '/content/pages';

        return new FileDriver(
            $this->makeContentParser(),
            new JsonPageIndex($this->pageIndexJsonPath()),
            new PageIndexer(),
            $contentPath,
        );
    }

    public function test_save_load_and_delete_page(): void
    {
        $driver = $this->driver();
        $page = new Page('welcome', 'Welcome', 'Hello world', 'published');

        $this->assertTrue($driver->save($page));
        $this->assertTrue($driver->exists('welcome'));

        $loaded = $driver->load('welcome');
        $this->assertSame('Welcome', $loaded->title);
        $this->assertStringContainsString('Hello world', $loaded->content);

        $this->assertTrue($driver->delete('welcome'));
        $this->assertFalse($driver->exists('welcome'));
    }

    public function test_throws_exception_when_loading_nonexistent_page(): void
    {
        $driver = $this->driver();
        $this->expectException(\Anvyr\Loom\Exceptions\NotFoundException::class);
        $driver->load('nonexistent');
    }

    public function test_throws_exception_when_saving_invalid_page(): void
    {
        $driver = $this->driver();
        $this->expectException(\Anvyr\Loom\Exceptions\ValidationException::class);

        // Page with empty slug
        $page = new Page(
            slug: '',
            title: 'Test',
            content: 'Content'
        );

        $driver->save($page);
    }

    public function test_can_update_existing_page(): void
    {
        $driver = $this->driver();
        $page = new Page('update-test', 'Original', 'Original content');
        $driver->save($page);

        // Update
        $page->title = 'Updated';
        $page->content = 'Updated content';
        $driver->save($page);

        $loaded = $driver->load('update-test');

        $this->assertSame('Updated', $loaded->title);
        $this->assertSame('Updated content', $loaded->content);
    }

    public function test_list_returns_metadata_only_pages(): void
    {
        $driver = $this->driver();
        $driver->save(new Page('page-a', 'Page A', 'Content of A', 'published'));
        $driver->save(new Page('page-b', 'Page B', 'Content of B', 'draft'));

        $all = $driver->list();
        $this->assertSame(2, $all->count());

        $published = $driver->list(['status' => 'published']);
        $this->assertSame(1, $published->count());
        $this->assertSame('Page A', $published->first()->title);

        // list() returns metadata-only pages (no content parsing)
        $this->assertSame('', $published->first()->content);
    }

    public function test_load_returns_fully_hydrated_page(): void
    {
        $driver = $this->driver();
        $driver->save(new Page('hydrated', 'Hydrated', 'Some **bold** text', 'published'));

        $loaded = $driver->load('hydrated');
        $this->assertSame('Hydrated', $loaded->title);
        $this->assertNotEmpty($loaded->content);
        $this->assertNotEmpty($loaded->html());
    }

    public function test_count_with_filters(): void
    {
        $driver = $this->driver();
        $driver->save(new Page('pub-1', 'Published 1', 'Content', 'published'));
        $driver->save(new Page('pub-2', 'Published 2', 'Content', 'published'));
        $driver->save(new Page('draft-1', 'Draft 1', 'Content', 'draft'));

        $this->assertSame(3, $driver->count());
        $this->assertSame(2, $driver->count(['status' => 'published']));
        $this->assertSame(1, $driver->count(['status' => 'draft']));
    }
}
