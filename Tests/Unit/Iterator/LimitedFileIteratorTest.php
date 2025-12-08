<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mailqueue".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace CPSIT\Typo3Mailqueue\Tests\Unit\Iterator;

use CPSIT\Typo3Mailqueue as Src;
use PHPUnit\Framework;
use TYPO3\TestingFramework;

/**
 * LimitedFileIteratorTest
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Iterator\LimitedFileIterator::class)]
final class LimitedFileIteratorTest extends TestingFramework\Core\Unit\UnitTestCase
{
    #[Framework\Attributes\Test]
    public function iteratorReturnsOnlyFilesWithAcceptedSuffixes(): void
    {
        $directory = \dirname(__DIR__) . '/Fixtures/Messages';
        $subject = new Src\Iterator\LimitedFileIterator(
            new \DirectoryIterator($directory),
            [
                '.message',
                '.message.sending',
            ],
        );

        $expected = [
            $directory . '/test-1.message',
            $directory . '/test-2.message',
            $directory . '/test-3.message.sending',
        ];
        $actual = [];

        foreach ($subject as $fileInfo) {
            self::assertInstanceOf(\DirectoryIterator::class, $fileInfo);

            $actual[] = $fileInfo->getRealPath();
        }

        \sort($actual);

        self::assertSame($expected, $actual);
    }
}
