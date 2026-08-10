<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Tests\Unit\Manipulation;

use HTML\Sourceopt\Manipulation\RemoveGenerator;
use HTML\Sourceopt\Tests\Unit\AbstractUnitTest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
#[CoversNothing]
class RemoveGeneratorTest extends AbstractUnitTest
{
    #[DataProvider('generatorProvider')]
    public function testRemoveGenerator(string $before, string $after): void
    {
        $cleanService = new RemoveGenerator();
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
</head>',
                '<head>
<meta name="Regisseur" content="Peter Jackson">

</head>',
            ],
            [
                '<head>
<meta name="Regisseur" content="Peter Jackson">
<meta name="generator" content="TYPO3 CMS" />
</head>',
                '<head>
<meta name="Regisseur" content="Peter Jackson">

</head>',
            ],
            [
                '<head>
<meta name="Regisseur" content="Peter Jackson">
<meta name="other" content="generator" />
</head>',
                '<head>
<meta name="Regisseur" content="Peter Jackson">
<meta name="other" content="generator" />
</head>',
            ],
        ];
    }
}
