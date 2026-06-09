<?php

declare(strict_types=1);

namespace Anvyr\Loom\Content\Index;

final readonly class PageIndexQuery
{
    private const ALLOWED_ORDER_FIELDS = [
        'created_at',
        'updated_at',
        'published_at',
        'slug',
        'title',
        'status',
    ];

    public function __construct(
        public ?string $status = null,
        public ?int $limit = null,
        public int $offset = 0,
        public string $orderBy = 'created_at',
        public string $orderDirection = 'desc',
    ) {
    }

    /** @param array<string, mixed> $filters */
    public static function fromFilters(array $filters = []): self
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : null;
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $orderBy = (string) ($filters['order_by'] ?? 'created_at');
        $orderDirection = strtolower((string) ($filters['order_dir'] ?? 'desc'));

        if (!in_array($orderBy, self::ALLOWED_ORDER_FIELDS, true)) {
            $orderBy = 'created_at';
        }

        if (!in_array($orderDirection, ['asc', 'desc'], true)) {
            $orderDirection = 'desc';
        }

        return new self(
            status: isset($filters['status']) ? (string) $filters['status'] : null,
            limit: $limit !== null && $limit >= 0 ? $limit : null,
            offset: $offset,
            orderBy: $orderBy,
            orderDirection: $orderDirection,
        );
    }
}
