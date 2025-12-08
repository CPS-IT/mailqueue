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

namespace CPSIT\Typo3Mailqueue\Tests\Unit\Mail;

use CPSIT\Typo3Mailqueue as Src;
use PHPUnit\Framework;
use Symfony\Component\Mailer;
use TYPO3\TestingFramework;

/**
 * TransportFailureTest
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Mail\TransportFailure::class)]
final class TransportFailureTest extends TestingFramework\Core\Unit\UnitTestCase
{
    #[Framework\Attributes\Test]
    public function fromExceptionReturnsTransportFailureForGivenException(): void
    {
        $exception = new Mailer\Exception\TransportException('Something went wrong.');

        $expected = new Src\Mail\TransportFailure(
            Mailer\Exception\TransportException::class,
            'Something went wrong.',
            new \DateTimeImmutable(),
        );

        self::assertEqualsWithDelta($expected, Src\Mail\TransportFailure::fromException($exception), 1);
    }

    #[Framework\Attributes\Test]
    public function fromFileThrowsExceptionIfFileDoesNotExist(): void
    {
        $this->expectExceptionObject(
            new Src\Exception\FileDoesNotExist('/foo/bar'),
        );

        Src\Mail\TransportFailure::fromFile('/foo/bar');
    }

    #[Framework\Attributes\Test]
    public function fromFileThrowsExceptionIfSerializedMetadataIsInvalid(): void
    {
        $file = \tempnam(sys_get_temp_dir(), 'mailqueue-tests-');

        file_put_contents($file, \serialize('foo'));

        $this->expectExceptionObject(
            new Src\Exception\SerializedFailureMetadataIsInvalid($file),
        );

        Src\Mail\TransportFailure::fromFile($file);

        \unlink($file);
    }

    #[Framework\Attributes\Test]
    public function fromFileReturnsUnserializedFileMetadata(): void
    {
        $file = \tempnam(sys_get_temp_dir(), 'mailqueue-tests-');
        $failure = new Src\Mail\TransportFailure(
            Mailer\Exception\TransportException::class,
            'Something went wrong.',
            new \DateTimeImmutable(),
        );

        file_put_contents($file, \serialize($failure));

        self::assertEquals($failure, Src\Mail\TransportFailure::fromFile($file));

        \unlink($file);
    }
}
