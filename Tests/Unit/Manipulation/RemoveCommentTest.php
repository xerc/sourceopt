<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Tests\Unit\Manipulation;

use HTML\Sourceopt\Manipulation\RemoveComments;
use HTML\Sourceopt\Tests\Unit\AbstractUnitTest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
#[CoversNothing]
class RemoveCommentTest extends AbstractUnitTest
{
    #[DataProvider('generatorProvider')]
    public function testRemoveComment(string $before, string $after): void
    {
        $cleanService = new RemoveComments();
        $result = $cleanService->manipulate($before);

        self::assertSame($after, $result);
    }

    public static function generatorProvider(): array
    {
        return [
            [
                '<head>
<meta name="Regisseur" content="Peter Jackson">
<meta name="generator" content="Tester">
<!-- Ich bin ein Test -->
</head>',
                '<head>
<meta name="Regisseur" content="Peter Jackson">
<meta name="generator" content="Tester">

</head>',
            ],
            [
                '<head>
<!-- Ich bin ein Test -->
<meta name="Regisseur" content="Peter Jackson">
<meta name="generator" content="Tester">
<!-- Ich bin ein Test -->
</head>',
                '<head>

<meta name="Regisseur" content="Peter Jackson">
<meta name="generator" content="Tester">

</head>',
            ],
        ];
    }

    #[DataProvider('keepProvider')]
    public function testKeepsWhiteListedComments(array $keep, string $before, string $after): void
    {
        $cleanService = new RemoveComments();
        $result = $cleanService->manipulate($before, ['keep.' => $keep]);

        self::assertSame($after, $result);
    }

    public static function keepProvider(): array
    {
        return [
            'pattern matches' => [
                ['/^TYPO3SEARCH_/usi'],
                '<div><!--TYPO3SEARCH_begin--><!-- Ich bin ein Test --></div>',
                '<div><!--TYPO3SEARCH_begin--></div>',
            ],
            'pattern does not match' => [
                ['/^SOMETHING_ELSE/usi'],
                '<div><!--TYPO3SEARCH_begin--></div>',
                '<div></div>',
            ],
        ];
    }
}
