<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Manipulation;

/**
 * HTML indenter, ported from Dindent (BSD-3-Clause).
 *
 * History: gajus/dindent -> schleuse/dindent -> optimized 4 `sourceopt`
 * @see https://github.com/gajus/dindent/blob/master/LICENSE (Anuary)
 * @see https://github.com/Schleuse/dindent/commits/main/src/Indenter.php
 * @see https://github.com/Schleuse/dindent/commit/af58465be3ad1d30aea8bfbae370f15c725d3df2 last commit ported from before #8/#9 were folded in by hand
 */
enum MatchType
{
    case IndentDecrease;
    case IndentIncrease;
    case IndentKeep;
}

final class ReformatHtml implements ManipulationInterface
{
    /** @see https://developer.mozilla.org/en-US/docs/Glossary/Void_element */
    public const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img',
        'input', 'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /** @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element#inline_text_semantics */
    private const INLINE_ELEMENTS = [
        'a', 'abbr', 'b', 'bdi', 'bdo', 'big', 'cite',
        'code', 'data', 'dfn', 'em', 'i', 'kbd', 'mark',
        'q', 's', 'samp', 'small', 'span', 'strong',
        'sub', 'sup', 'time', 'u', 'var', 'acronym', 'tt',
    ];

    /*
     * Ported from the pre-Dindent sourceopt formatHtml() box-element groupings.
     * `formatHtml` level 2-5 picks one of these as "elements that force their own
     * line"; anything in LOGIC_ELEMENTS but not selected for the active level is
     * folded into the inline set instead, so it stays on the surrounding line.
     * Level 5 ("max") keeps every element block, i.e. no folding at all.
     */
    private const STRUCTURE_ELEMENTS = ['html', 'head', 'body', 'div'];

    private const AESTHETIC_ELEMENTS = [
        'html', 'head', 'body', 'meta', 'title', 'div', 'table',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'form', 'pre', 'center',
    ];

    private const LOGIC_ELEMENTS = [
        'address', 'blockquote', 'center', 'dir', 'div', 'dl', 'fieldset', 'form',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'menu', 'noframes', 'noscript',
        'ol', 'p', 'pre', 'table', 'ul', 'article', 'aside', 'details', 'figcaption',
        'figure', 'footer', 'header', 'hgroup', 'nav', 'section',
        'dd', 'dt', 'frameset', 'li', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'colgroup',
        'applet', 'button', 'del', 'iframe', 'ins', 'map', 'object', 'script',
        'html', 'body', 'head', 'meta', 'title', 'link', 'base',
    ];

    private ?string $tab;

    private array $inline_elements;

    private array $temporary_replacements_format = [];

    private array $temporary_replacements_source = [];

    private array $temporary_replacements_inline = [];

    /**
     * @param string $html          The original HTML
     * @param array  $configuration `type` (1=no line breaks, 2=minimal, 3=aesthetic, 4=logic, 5=max) and `tab` character(s)
     *
     * @return string the indented HTML
     */
    public function manipulate(string $html, array $configuration = []): string
    {
        $formatType = (int) ($configuration['type'] ?? 0);
        $this->tab = 1 === $formatType ? null : (string) ($configuration['tab'] ?? "\t");

        $boxElements = match (true) {
            $formatType >= 5 => null,
            4 === $formatType => self::LOGIC_ELEMENTS,
            3 === $formatType => self::AESTHETIC_ELEMENTS,
            default => self::STRUCTURE_ELEMENTS,
        };

        $this->inline_elements = null === $boxElements
            ? self::INLINE_ELEMENTS
            : array_values(array_unique([
                ...self::INLINE_ELEMENTS,
                ...array_diff(self::LOGIC_ELEMENTS, $boxElements),
            ]));

        $this->temporary_replacements_format = [];
        $this->temporary_replacements_source = [];
        $this->temporary_replacements_inline = [];

        $input = $html;

        // Remove trailing spaces
        $input = preg_replace('/\h+$/m', '', $input);

        $count = 0;
        // Dindent does not touch `<pre|textarea>` body. Instead, it temporary removes it from the code, indents the input, and restores the body.
        $input = preg_replace_callback(
            '/(?<elm><(pre|textarea)[^>]*>)(?<str>[\s\S]*?)(?=<\/\2>)/i',
            function ($match) use (&$count): string {
                if (empty($match['str'])) {
                    return $match[0];
                }
                $this->temporary_replacements_format[] = $match;

                return $match['elm'] . 'ᐂᐂᐂ' . $count++ . 'ᐂᐂᐂ';
            },
            $input,
        );

        // Remove empty lines
        $input = preg_replace('/^\n+/m', '', $input);

        $count = 0;
        // Dindent does not touch `<!-- -->` body. Instead, it temporary removes it from the code, indents the input, and restores the body.
        $input = preg_replace_callback(
            '/(?<=<!--)\s*(?<str>[\s\S]+?)\s*(?=-->)/',
            function ($match) use (&$count): string {
                if (empty($match['str'])) {
                    return $match[0];
                }
                if (str_contains($match['str'], "\n")) {
                    $match['lf'] = true;
                    $match['str'] = "\n" . preg_replace('/^\s+|\s+$/m', '', $match['str']);
                } else {
                    $match['lf'] = false;
                    $match['str'] = ' ' . $match['str'] . ' ';
                }

                $this->temporary_replacements_source[] = $match;

                return 'ᐄᐄᐄ' . $count++ . 'ᐄᐄᐄ';
            },
            $input,
        );

        // Dindent does not indent `<script|style>` body. Instead, it temporary removes it from the code, indents the input, and restores the body.
        $input = preg_replace_callback(
            '/(?<elm><(script|style)[^>]*>)(?<str>[\s\S]*?)(?<lf>\n?)\s*(?=<\/\2>)/i',
            function ($match) use (&$count): string {
                if (empty($match['str'])) {
                    return $match[0];
                }
                $this->temporary_replacements_source[] = $match;

                return $match['elm'] . 'ᐄᐄᐄ' . $count++ . 'ᐄᐄᐄ';
            },
            $input,
        );

        // Shrink global whitespace
        $input = preg_replace('/\s+/', ' ', $input);
        // Remove leading whitespace
        $input = preg_replace('/^ /m', '', $input);

        $count = 0;
        // Temporary remove inline elements
        $input = preg_replace_callback(
            '/(?<elm><(' . implode('|', $this->inline_elements) . ')[^>]*>)\s*(?<str>[^<]*?)\s*(?<clt><\/\2>)/i',
            function ($match) use (&$count): string {
                if (empty($match['str'])) {
                    return $match[0];
                }
                $this->temporary_replacements_inline[] = sprintf('%s%s%s', $match['elm'], $match['str'], $match['clt']);

                return 'ᐃᐃᐃ' . $count++ . 'ᐃᐃᐃ';
            },
            $input,
        );

        $output = '';
        $indent = '';

        // NO line-break mode!
        if (null === $this->tab) {
            $output = str_replace("\n", '', $input);
        } else {
            // Discard useless whitespace before the tokenizer can turn it into its own indented line
            $input = preg_replace('/(<[^>]+>) (?=<)/', '$1', $input);
            $subject = preg_replace_callback(
                '/<!DOCTYPE[^>]+>/i',
                function ($match) use (&$output): string {
                    $output = $match[0] . "\n";

                    return '';
                },
                $input,
            );

            $indLen = -1 * strlen($this->tab);
            $patterns = [
                // comment
                '/\G<!--[\s\S]*?-->/' => MatchType::IndentKeep,
                // standart element
                '/\G<([a-z][\w\-]*)(?: [^<]*)?>[^<]*<\/\1>/' => MatchType::IndentKeep,
                // implied closing
                '/\G<(?:' . implode('|', self::VOID_ELEMENTS) . ')[^>]*>/' => MatchType::IndentKeep,
                // self-closing
                '/\G<[^>]+\/>/' => MatchType::IndentKeep,
                // closing tag
                '/\G<\/[^>]+>/' => MatchType::IndentDecrease,
                // opening tag
                '/\G<[^>]+>/' => MatchType::IndentIncrease,
                // text node
                '/\G[^<]+/' => MatchType::IndentKeep,
            ];

            $offset = 0;
            $subjectLength = strlen($subject);

            while ($offset < $subjectLength) {
                $matched = false;

                foreach ($patterns as $pattern => $rule) {
                    if (preg_match($pattern, $subject, $matches, 0, $offset)) {
                        $matched = true;
                        $offset += strlen($matches[0]);

                        switch ($rule) {
                            case MatchType::IndentIncrease:
                                $output .= $indent . $matches[0] . "\n";
                                $indent .= $this->tab;
                                break;

                            case MatchType::IndentDecrease:
                                $indent = substr($indent, 0, $indLen);
                                // no break
                            case MatchType::IndentKeep:
                                $output .= $indent . $matches[0] . "\n";
                                break;
                        }

                        break;
                    }
                }

                if (!$matched) {
                    $output .= substr($subject, $offset);
                    break;
                }
            }
        }

        // Restore inline elements
        if (!empty($this->temporary_replacements_inline)) {
            $output = preg_replace_callback(
                '/ᐃᐃᐃ(\d+)ᐃᐃᐃ/',
                function ($match): string {
                    return $this->temporary_replacements_inline[(int) $match[1]] ?? $match[0];
                },
                $output,
            );
        }

        // Remove empty space inside & between tags
        $output = preg_replace('/(<[^>]+>) (?=<)/', '$1', $output);

        // Restore `<pre|textarea>`.
        if (!empty($this->temporary_replacements_format)) {
            $output = preg_replace_callback(
                '/( *)(<[^>]+>?)?ᐂᐂᐂ(\d+)ᐂᐂᐂ/',
                function ($match): string {
                    $original = $this->temporary_replacements_format[(int) $match[3]] ?? null;

                    return null === $original ? $match[0] : $match[1] . $match[2] . $original['str'];
                },
                $output,
            );
        }

        // Restore `<script|style>` & `<!-- -->`
        if (!empty($this->temporary_replacements_source)) {
            foreach (array_reverse($this->temporary_replacements_source, true) as $i => $original) {
                $output = preg_replace(
                    '/( +)?(<[^>]+>?)?ᐄᐄᐄ' . $i . 'ᐄᐄᐄ/m',
                    preg_replace('/^/m', '\\$1', '$2' . $original['str']) . (!empty($original['lf']) ? "\n$1" : ''),
                    $output,
                );
            }
        }

        return rtrim($output);
    }
}
