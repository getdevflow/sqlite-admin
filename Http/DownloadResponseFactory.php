<?php

declare(strict_types=1);

namespace Plugin\SqliteAdmin\Http;

use Qubus\Http\Response;

final class DownloadResponseFactory
{
    public function make(
        string $contents,
        string $filename,
        string $contentType = 'application/octet-stream'
    ): Response {
        $response = new Response();

        $response->getBody()->write($contents);

        return $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $this->safeFilename($filename) . '"')
            ->withHeader('Content-Length', (string) strlen($contents))
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', '0');
    }

    private function safeFilename(string $filename): string
    {
        return str_replace(["\r", "\n", '"'], '', basename($filename));
    }
}
