<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Models;

use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Tests\Support\TestCase;
use DateTime;

final class PageTest extends TestCase
{
    public function test_can_create_page_with_required_fields(): void
    {
        $page = new Page(
            slug: 'test-page',
            title: 'Test Page',
            content: '# Hello World'
        );

        $this->assertSame('test-page', $page->slug);
        $this->assertSame('Test Page', $page->title);
        $this->assertSame('# Hello World', $page->content);
        $this->assertSame('draft', $page->status);
    }

    public function test_can_create_page_from_array(): void
    {
        $data = [
            'slug' => 'about',
            'title' => 'About Us',
            'content' => 'Our story',
            'status' => 'published',
            'layout' => 'default',
            'excerpt' => 'Short description',
            'created_at' => '2025-01-01 10:00:00',
        ];

        $page = Page::fromArray($data);

        $this->assertSame('about', $page->slug);
        $this->assertSame('About Us', $page->title);
        $this->assertSame('Our story', $page->content);
        $this->assertSame('published', $page->status);
        $this->assertSame('default', $page->layout);
        $this->assertSame('Short description', $page->excerpt);
        $this->assertInstanceOf(DateTime::class, $page->createdAt);
    }

    public function test_can_convert_page_to_array(): void
    {
        $page = new Page(
            slug: 'test',
            title: 'Test',
            content: 'Content'
        );

        $array = $page->toArray();

        $this->assertIsArray($array);
        $this->assertSame('test', $array['slug']);
        $this->assertSame('Test', $array['title']);
        $this->assertSame('Content', $array['content']);
    }

    public function test_can_set_and_get_html(): void
    {
        $page = new Page('test', 'Test', 'Content');
        $page->setHtml('<h1>Rendered HTML</h1>');

        $this->assertSame('<h1>Rendered HTML</h1>', $page->html());
    }

    public function test_can_get_excerpt(): void
    {
        $page = new Page(
            slug: 'test',
            title: 'Test',
            content: 'This is a very long content that should be truncated to a shorter excerpt'
        );

        $excerpt = $page->getExcerpt(20);

        $this->assertSame('This is a very long ...', $excerpt);
    }

    public function test_can_set_and_get_meta(): void
    {
        $page = new Page('test', 'Test', 'Content');
        $page->setMeta('author', 'John Doe');
        $page->setMeta('tags', ['php', 'cms']);

        $this->assertSame('John Doe', $page->getMeta('author'));
        $this->assertSame(['php', 'cms'], $page->getMeta('tags'));
        $this->assertNull($page->getMeta('nonexistent'));
        $this->assertSame('default', $page->getMeta('nonexistent', 'default'));
    }

    public function test_can_check_if_published(): void
    {
        $page = new Page('test', 'Test', 'Content', 'published');
        $this->assertTrue($page->isPublished());

        $page = new Page('test', 'Test', 'Content', 'draft');
        $this->assertFalse($page->isPublished());
    }
}
