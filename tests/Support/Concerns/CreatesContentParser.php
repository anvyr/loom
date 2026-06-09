<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Concerns;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Services\ContentParser;
use Anvyr\Loom\Services\Parsers\CommonMarkParser;

/**
 * Factory for building a ContentParser backed by a temp FileCache.
 *
 * Requires the using class to provide `makeFileCache()` (from TestCase).
 */
trait CreatesContentParser
{
    protected function makeContentParser(array $commonMarkOptions = []): ContentParser
    {
        return new ContentParser(
            $this->makeFileCache(),
            new CommonMarkParser($commonMarkOptions),
            Application::getInstance()->make(ConfigRepository::class),
        );
    }
}
