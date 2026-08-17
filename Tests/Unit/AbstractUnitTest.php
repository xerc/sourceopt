<?php

declare(strict_types=1);

namespace HTML\Sourceopt\Tests\Unit;

use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;

abstract class AbstractUnitTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Plain PHPUnit\Framework\TestCase never bootstraps TYPO3, so Environment::getContext()
        // would otherwise return null here for any test hitting code that calls it.
        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            __DIR__,
            __DIR__,
            sys_get_temp_dir(),
            sys_get_temp_dir(),
            __FILE__,
            PHP_OS_FAMILY === 'Windows' ? 'WINDOWS' : 'UNIX',
        );
    }
}
