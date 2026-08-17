<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Tests\Unit\Service;

use HTML\Sourceopt\Service\CleanHtmlService;
use HTML\Sourceopt\Tests\Unit\AbstractUnitTest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
#[CoversNothing]
class CleanHtmlServiceTest extends AbstractUnitTest
{
    public function testFormatHtml(): void
    {
        $cleanService = new CleanHtmlService();
        $config = [
            'enabled' => true,
            'removeComments' => true,
            'formatHtml' => 4,
            'formatHtml.' => [
                'tabSize' => 2,
            ],
        ];

        $svg =
'<svg>
  <path/>
  <path/>
  <path>
    <path/>
    <path>
      <path>
        <path></path>
      </path>
    </path>
  </path>
</svg>';

        self::assertSame($svg, $cleanService->clean($svg, $config));
    }

    /**
     * The doctype used to come from $GLOBALS['TSFE'], it is now passed in.
     */
    #[DataProvider('doctypeProvider')]
    public function testSelfClosingTagsDependOnDoctype(string $doctype, string $expected): void
    {
        $cleanService = new CleanHtmlService();
        $html = '<head><meta name="viewport" content="width=device-width" /></head>';

        self::assertSame($expected, $cleanService->clean($html, ['formatHtml' => 0], $doctype));
    }

    public static function doctypeProvider(): array
    {
        return [
            'html5 drops the self-closing slash' => [
                '',
                '<head><meta name="viewport" content="width=device-width"></head>',
            ],
            'xhtml keeps it' => [
                'xhtml',
                '<head><meta name="viewport" content="width=device-width" /></head>',
            ],
        ];
    }

    public function testInvalidUtf8IsRejected(): void
    {
        $cleanService = new CleanHtmlService();

        $this->expectException(\Exception::class);
        $cleanService->clean("<head>\xC3\x28</head>");
    }

    /**
     * formatHtml levels 2-5 pick a different set of "box" elements that get their
     * own line; below that threshold, elements like h1/p fold onto the surrounding
     * line instead (h1/p only become box elements starting at level 3).
     */
    #[DataProvider('formatHtmlLevelProvider')]
    public function testFormatHtmlLevelsControlLineBreakGranularity(int $level, string $expected): void
    {
        $cleanService = new CleanHtmlService();
        $html = '<div><h1>Title</h1><p>Text</p></div>';

        self::assertSame($expected, $cleanService->clean($html, ['formatHtml' => $level]));
    }

    public static function formatHtmlLevelProvider(): array
    {
        return [
            'level 1: single line' => [1, '<div><h1>Title</h1><p>Text</p></div>'],
            'level 2: h1/p not yet box elements, still folded' => [2, '<div><h1>Title</h1><p>Text</p></div>'],
            'level 3: h1/p become box elements, each breaks' => [3, "<div>\n\t<h1>Title</h1>\n\t<p>Text</p>\n</div>"],
        ];
    }
}
