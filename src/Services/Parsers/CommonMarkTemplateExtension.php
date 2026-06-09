<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services\Parsers;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Preserves {{ }} and {!! !!} template tags through Markdown parsing.
 */
class CommonMarkTemplateExtension implements ExtensionInterface, InlineParserInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 20);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::oneOf('{{', '{!!');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        $char = $cursor->peek();
        if ($char !== '{') {
            return false;
        }

        $secondChar = $cursor->peek(1);

        if ($secondChar === '{') {
            $endSequence = '}}';
            $startSequence = '{{';
        } elseif ($secondChar === '!') {
            $endSequence = '!!}';
            $startSequence = '{!!';
        } else {
            return false;
        }

        $advance = ($startSequence === '{!!') ? 3 : 2;
        $savedState = $cursor->saveState();
        $cursor->advanceBy($advance);

        $content = '';
        $endRegex = '/^' . preg_quote($endSequence, '/') . '/';

        while (!$cursor->isAtEnd()) {
            if ($cursor->match($endRegex)) {
                $startSequence = ($endSequence === '}}') ? '{{' : '{!!';

                $fullTag = $startSequence . $content . $endSequence;
                $inlineContext->getContainer()->appendChild(new HtmlInline($fullTag));
                return true;
            }

            $content .= $cursor->getCharacter();
            $cursor->advance();
        }

        $cursor->restoreState($savedState);
        return false;
    }
}
