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

namespace CPSIT\Typo3Mailqueue\Mail;

use CPSIT\Typo3Mailqueue\Exception;
use Symfony\Component\Mailer;

/**
 * TransportFailure
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final readonly class TransportFailure
{
    public function __construct(
        public string $exception,
        public string $message,
        public \DateTimeImmutable $date,
    ) {}

    public static function fromException(Mailer\Exception\TransportExceptionInterface $exception): self
    {
        return new self(
            $exception::class,
            $exception->getMessage(),
            new \DateTimeImmutable(),
        );
    }

    /**
     * @throws Exception\FileDoesNotExist
     * @throws Exception\SerializedFailureMetadataIsInvalid
     */
    public static function fromFile(string $file): self
    {
        if (!file_exists($file)) {
            throw new Exception\FileDoesNotExist($file);
        }

        $failure = unserialize((string)file_get_contents($file), [
            'allowedClasses' => [
                \DateTimeImmutable::class,
            ],
        ]);

        if (!($failure instanceof self)) {
            throw new Exception\SerializedFailureMetadataIsInvalid($file);
        }

        return $failure;
    }
}
