<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core\Tenancy;

final class TenantContext
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $host = null,
        private readonly ?string $pathPrefix = null,
        private readonly ?string $urlPrefix = null,
        /** @var array<string, mixed> */
        private readonly array $metadata = []
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function host(): ?string
    {
        return $this->host;
    }

    public function pathPrefix(): ?string
    {
        return $this->pathPrefix;
    }

    public function urlPrefix(): ?string
    {
        return $this->urlPrefix;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
