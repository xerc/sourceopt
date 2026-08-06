<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use TYPO3\CMS\Core\Http\NullResponse;
use TYPO3\CMS\Core\Http\Stream;

abstract class AbstractMiddleware implements MiddlewareInterface
{
    protected function responseIsAlterable(ResponseInterface $response): bool
    {
        if ($response instanceof NullResponse) {
            return false;
        }

        if ('text/html' !== substr($response->getHeaderLine('Content-Type'), 0, 9)) {
            return false;
        }

        if (empty($response->getBody())) {
            return false;
        }

        return true;
    }

    /**
     * TypoScript "config." of the current page; empty outside a rendered frontend.
     */
    protected function getTypoScriptConfig(ServerRequestInterface $request): array
    {
        return $request->getAttribute('frontend.typoscript')?->getConfigArray() ?? [];
    }

    protected function getStringStream(string $content): StreamInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write($content);

        return $body;
    }
}
