<?php

declare(strict_types=1);

namespace Anvyr\Loom\Models;

use DateTime;

class Page
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $content,
        public string $status = 'draft',
        public ?string $layout = null,
        public ?string $excerpt = null,
        public bool $trusted = false,
        /** @var array<string, mixed> */
        public array $meta = [],
        public ?DateTime $createdAt = null,
        public ?DateTime $updatedAt = null,
        public ?DateTime $publishedAt = null,
        private ?string $renderedHtml = null,
        public string $id = ''
    ) {
        if ($this->id === '') {
            $this->id = uuid_v7();
        }
        $this->createdAt ??= new DateTime();
        $this->updatedAt ??= new DateTime();
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uuid_v7(),
            slug: $data['slug'] ?? '',
            title: $data['title'] ?? 'Untitled',
            content: $data['content'] ?? '',
            status: $data['status'] ?? 'draft',
            layout: $data['layout'] ?? null,
            excerpt: $data['excerpt'] ?? null,
            trusted: (bool) ($data['trusted'] ?? false),
            meta: $data['meta'] ?? [],
            createdAt: isset($data['created_at']) ? self::parseDateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? self::parseDateTime($data['updated_at']) : null,
            publishedAt: isset($data['published_at']) ? self::parseDateTime($data['published_at']) : null
        );
    }

    private static function parseDateTime(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if (is_numeric($value)) {
            // Unix timestamp
            $dt = new DateTime();
            $dt->setTimestamp((int) $value);
            return $dt;
        }

        if (is_string($value)) {
            try {
                return new DateTime($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status,
            'layout' => $this->layout,
            'excerpt' => $this->excerpt,
            'trusted' => $this->trusted,
            'meta' => $this->meta,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function html(): string
    {
        return $this->renderedHtml ?? $this->content;
    }

    public function setHtml(string $html): self
    {
        $this->renderedHtml = $html;
        return $this;
    }

    public function getExcerpt(int $length = 160): string
    {
        if ($this->excerpt) {
            return $this->excerpt;
        }

        // Strip HTML tags and get first X characters
        $text = strip_tags($this->html());
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function setMeta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function publish(): self
    {
        $this->status = 'published';
        $this->publishedAt = new DateTime();
        return $this;
    }

    public function unpublish(): self
    {
        $this->status = 'draft';
        return $this;
    }
}
