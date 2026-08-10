<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Middleware;

use HTML\Sourceopt\Service\RegExRepService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RegExRepMiddleware extends AbstractMiddleware
{
    /**
     * RegEx search & replace @ HTML output.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $config = $this->getTypoScriptConfig($request);

        if ($this->responseIsAlterable($response) && ($config['replacer.'] ?? false)) {
            $regExRepService = GeneralUtility::makeInstance(RegExRepService::class);
            $processedHtml = $regExRepService->process((string) $response->getBody(), (array) $config['replacer.'], $request);
            $response = $response->withBody($this->getStringStream($processedHtml));
        }

        return $response;
    }
}
