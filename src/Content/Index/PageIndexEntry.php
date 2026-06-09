<?php

declare(strict_types=1);

namespace Anvyr\Loom\Content\Index;

use Anvyr\Loom\Models\Page;

final readonly class PageIndexEntry
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $path,
        public int $mtime,
        public string $format,
        public string $title,
        public string $status,
        public ?string $layout,
        public ?string $excerpt,
        public bool $trusted,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $publishedAt,
        /** @var array<string, mixed> */
        public array $meta,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? uuid_v7()),
            slug: (string) $data['slug'],
            path: (string) $data['path'],
            mtime: (int) $data['mtime'],
            format: (string) ($data['format'] ?? 'auto'),
            title: (string) $data['title'],
            status: (string) $data['status'],
            layout: isset($data['layout']) ? (string) $data['layout'] : null,
            excerpt: isset($data['excerpt']) ? (string) $data['excerpt'] : null,
            trusted: (bool) ($data['trusted'] ?? false),
            createdAt: isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            publishedAt: isset($data['published_at']) ? (string) $data['published_at'] : null,
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'path' => $this->path,
            'mtime' => $this->mtime,
            'format' => $this->format,
            'title' => $this->title,
            'status' => $this->status,
            'layout' => $this->layout,
            'excerpt' => $this->excerpt,
            'trusted' => $this->trusted,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'published_at' => $this->publishedAt,
            'meta' => $this->meta,
        ];
    }

    public function toPage(): Page
    {
        return Page::fromArray([
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => '',
            'status' => $this->status,
            'layout' => $this->layout,
            'excerpt' => $this->excerpt,
            'trusted' => $this->trusted,
            'meta' => $this->meta,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'published_at' => $this->publishedAt,
        ]);
    }

    public function sortValue(string $field): string
    {
        return match ($field) {
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status,
            'updated_at' => $this->updatedAt ?? '',
            'published_at' => $this->publishedAt ?? '',
            default => $this->createdAt ?? '',
        };
    }
}
