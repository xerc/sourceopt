<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Service;

use HTML\Sourceopt\Manipulation\ManipulationInterface;
use HTML\Sourceopt\Manipulation\ReformatHtml;
use HTML\Sourceopt\Manipulation\RemoveComments;
use HTML\Sourceopt\Manipulation\RemoveGenerator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service: Clean parsed HTML functionality
 * Based on the extension 'sourceopt'.
 */
class CleanHtmlService implements SingletonInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Enable Debug comment in footer.
     */
    protected bool $debugComment = false;

    /**
     * Format Type.
     */
    protected int $formatType = 0;

    /**
     * Remove the `generator` meta tag.
     */
    protected bool $removeGenerator = false;

    /**
     * Remove HTML comments.
     */
    protected bool $removeComments = false;

    /**
     * Tab character.
     */
    protected string $tab = "\t";

    /**
     * Newline character.
     */
    protected string $newline = "\n";

    /**
     * Configured extra header comment.
     */
    protected string $headerComment = '';

    /**
     * Empty space char.
     */
    protected string $emptySpaceChar = ' ';

    /**
     * Set variables based on given config.
     */
    public function setVariables(array $config): void
    {
        if (isset($config['headerComment']) && !empty($config['headerComment'])) {
            $this->headerComment = $config['headerComment'];
        }

        if (isset($config['removeGenerator'])) {
            $this->removeGenerator = (bool) $config['removeGenerator'];
        }

        if (isset($config['removeComments'])) {
            $this->removeComments = (bool) $config['removeComments'];
        }

        if (isset($config['formatHtml']) && is_numeric($config['formatHtml'])) {
            $this->formatType = (int) $config['formatHtml'];
        }

        if (isset($config['formatHtml.']['tabSize']) && is_numeric($config['formatHtml.']['tabSize'])) {
            $this->tab = str_pad('', (int) $config['formatHtml.']['tabSize'], ' ');
        }

        if (isset($config['formatHtml.']['debugComment'])) {
            $this->debugComment = (bool) $config['formatHtml.']['debugComment'];
        }

        if (isset($config['dropEmptySpaceChar']) && (bool) $config['dropEmptySpaceChar']) {
            $this->emptySpaceChar = '';
        }
    }

    /**
     * Extract first invalid UTF-8 charater surroundings.
     */
    private function getInvalidUTF8Snipped(string $html, int $excerpt = 42): string
    {
        mb_internal_encoding('UTF-8');
        mb_substitute_character(0xFFFD);

        $html   = mb_convert_encoding($html, 'UTF-8');
        $html   = preg_replace('/\s+/', ' ', $html);

        // $count  = preg_match_all('/\x{FFFD}/u', $html);
        $pos    = mb_strpos($html, mb_chr(0xFFFD, 'UTF-8'));

        return mb_substr($html, max(0, $pos - $excerpt), ($excerpt << 1));
    }

    /**
     * Clean given HTML with formatter.
     *
     * @param string $doctype TypoScript "config.doctype" of the current page
     */
    public function clean(string $html, array $config = [], string $doctype = ''): string
    {
        if (!mb_check_encoding($html, 'UTF-8')) {
            $message = 'Invalid UTF-8 detected @ ' . $this->getInvalidUTF8Snipped($html);

            if (Environment::getContext()->isProduction()) {
                $this->logger?->error($message);

                return $html;
            }

            throw new \Exception($message);
        }

        if (!empty($config)) {
            $this->setVariables($config);
        }

        // convert line-breaks to UNIX
        $html = preg_replace("(\r\n|\r)", $this->newline, $html);

        // include configured header comment in HTML content block
        if (!empty($this->headerComment)) {
            $html = preg_replace('/^(-->)$/m', "\n\t" . $this->headerComment . "\n$1", $html, 1);
        }

        // cleanup HTML5 self-closing elements
        if ('x' !== substr($doctype, 0, 1)) {
            $html = preg_replace(
                '/<((?:' . implode('|', ReformatHtml::VOID_ELEMENTS) . ')\s[^>]+?)\s*\\\?\/>/',
                '<$1>',
                $html
            );
        }

        $manipulations = [];

        if ($this->removeGenerator) {
            $manipulations['removeGenerator'] = GeneralUtility::makeInstance(RemoveGenerator::class);
        }

        if ($this->removeComments) {
            $manipulations['removeComments'] = GeneralUtility::makeInstance(RemoveComments::class);
        }

        foreach ($manipulations as $key => $manipulation) {
            /** @var ManipulationInterface $manipulation */
            $configuration = isset($config[$key . '.']) && \is_array($config[$key . '.']) ? $config[$key . '.'] : [];
            $html = $manipulation->manipulate($html, $configuration);
        }

        if ($this->formatType) {
            $html = GeneralUtility::makeInstance(ReformatHtml::class)
                ->manipulate($html, ['type' => $this->formatType, 'tab' => $this->tab]);
        }

        // recover line-breaks
        if (Environment::isWindows()) {
            $html = str_replace($this->newline, "\r\n", $html);
        }

        return (string) $html;
    }
}
