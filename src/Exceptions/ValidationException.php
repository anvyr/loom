<?php

declare(strict_types=1);

namespace Anvyr\Loom\Exceptions;

use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

class ValidationException extends HttpException
{
    public function __construct(
        /** @var array<string, list<string>> */
        private array $errors,
        string $message = 'Validation failed'
    ) {
        parent::__construct(422, $message);
    }

    /** @return array<string, list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toResponse(Request $request): Response
    {
        $payload = [
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'status' => 422,
        ];

        return $request->expectsJson()
            ? Response::json($payload, 422)
            : Response::error($this->formatHtml(), 422);
    }

    private function formatHtml(): string
    {
        $items = array_map(static fn (string $field, array $messages): string => '<li><strong>' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars(implode(', ', $messages), ENT_QUOTES, 'UTF-8') . '</li>', array_keys($this->errors), $this->errors);

        $list = implode('', $items);

        return <<<HTML
<h1>Validation Failed</h1>
<ul>{$list}</ul>
HTML;
    }
}
