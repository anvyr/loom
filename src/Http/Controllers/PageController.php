<?php

declare(strict_types=1);

namespace Anvyr\Loom\Http\Controllers;

use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Exceptions\NotFoundException;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Models\Page;
use Anvyr\Loom\Services\PageService;
use Anvyr\Loom\Services\ViewEngine;

class PageController
{
    public function __construct(
        private readonly PageService $pages,
        private readonly ViewEngine $view,
        private readonly ConfigRepository $config
    ) {
    }

    public function home(Request $request): Response
    {
        try {
            return $this->renderPage($this->pages->load('welcome'));
        } catch (\Exception) {
            return Response::html('<h1>Welcome to Anvyr Loom</h1><p>Create a welcome.md page to get started.</p>');
        }
    }

    public function show(Request $request, string $slug): Response
    {
        try {
            $page = $this->pages->load($slug);

            if (!$page->isPublished() && !(bool) $this->config->get('app.debug', false)) {
                return Response::notFound('Page not found');
            }

            return $this->renderPage($page);
        } catch (NotFoundException) {
            return Response::notFound('Page not found');
        }
    }

    private function renderPage(Page $page): Response
    {
        $layout = 'layouts/' . ($page->layout ?? 'default');
        $contentVars = ['page' => $page];
        $content = $page->trusted
            ? $this->view->compileString($page->html(), $contentVars)
            : $this->view->safe($page->html(), $contentVars);

        return Response::html($this->view->render($layout, [
            'page' => $page,
            'content' => $content,
        ]));
    }
}
