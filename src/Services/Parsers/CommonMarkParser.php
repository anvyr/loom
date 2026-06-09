<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services\Parsers;

use Anvyr\Loom\Contracts\ParserInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

class CommonMarkParser implements ParserInterface
{
    private MarkdownConverter $converter;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        $environment = new Environment(['html_input' => $config['html_input'] ?? 'allow']);
        $environment->addExtension(new CommonMarkCoreExtension());

        $ext = $config['extensions'] ?? [];
        if ($ext['table'] ?? true) {
            $environment->addExtension(new TableExtension());
        }
        if ($ext['strikethrough'] ?? true) {
            $environment->addExtension(new StrikethroughExtension());
        }
        if ($ext['autolink'] ?? true) {
            $environment->addExtension(new AutolinkExtension());
        }
        if ($ext['task_lists'] ?? true) {
            $environment->addExtension(new TaskListExtension());
        }

        $environment->addExtension(new CommonMarkTemplateExtension());
        $this->converter = new MarkdownConverter($environment);
    }

    public function parse(string $content): string
    {
        return (string) $this->converter->convert($content);
    }
}
