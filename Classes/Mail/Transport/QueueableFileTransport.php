<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "mailqueue".
 *
 * Copyright (C) 2024 Elias Häußler <e.haeussler@familie-redlich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace CPSIT\Typo3Mailqueue\Mail\Transport;

use CPSIT\Typo3Mailqueue\Exception;
use CPSIT\Typo3Mailqueue\Mail;
use DirectoryIterator;
use Symfony\Component\Mailer;
use Symfony\Component\Mime;
use Traversable;
use TYPO3\CMS\Core;

/**
 * QueueableFileTransport
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class QueueableFileTransport extends Core\Mail\FileSpool implements QueueableTransport
{
    public function recover(int $timeout = 900): void
    {
        $iterator = new DirectoryIterator($this->path);

        // Remove failure metadata
        foreach ($iterator as $file) {
            $path = (string)$file->getRealPath();

            if (str_ends_with($path, '.message.failure')) {
                unlink($path);
            }
        }

        // Recover stuck transports
        parent::recover($timeout);
    }

    /**
     * @throws Exception\SerializedMessageIsInvalid
     * @throws Mailer\Exception\TransportExceptionInterface
     */
    public function flushQueue(Mailer\Transport\TransportInterface $transport): int
    {
        $directoryIterator = new \DirectoryIterator($this->path);
        $time = time();
        $count = 0;

        foreach ($directoryIterator as $file) {
            $file = (string)$file->getRealPath();

            if (!str_ends_with($file, '.message')) {
                continue;
            }

            // We try a rename, it's an atomic operation, and avoid locking the file
            if (rename($file, $file . '.sending')) {
                $message = unserialize((string)file_get_contents($file . '.sending'), [
                    'allowedClasses' => [
                        Mime\RawMessage::class,
                        Mime\Message::class,
                        Mime\Email::class,
                        Mailer\DelayedEnvelope::class,
                        Mailer\Envelope::class,
                    ],
                ]);

                if (!($message instanceof Mailer\SentMessage)) {
                    throw new Exception\SerializedMessageIsInvalid($file);
                }

                try {
                    $transport->send($message->getMessage(), $message->getEnvelope());
                } catch (Mailer\Exception\TransportExceptionInterface $exception) {
                    $this->flagFailedTransport($file, $exception);

                    throw $exception;
                }

                $count++;

                unlink($file . '.sending');
            } else {
                // This message has just been caught by another process
                continue;
            }

            if ($this->getMessageLimit() && $count >= $this->getMessageLimit()) {
                break;
            }

            if ($this->getTimeLimit() && ($GLOBALS['EXEC_TIME'] - $time) >= $this->getTimeLimit()) {
                break;
            }
        }

        return $count;
    }

    private function flagFailedTransport(string $file, Mailer\Exception\TransportExceptionInterface $exception): void
    {
        $failure = Mail\TransportFailure::fromException($exception);

        file_put_contents($file . '.failure', serialize($failure));
    }

    public function getMailQueue(): Mail\Queue\MailQueue
    {
        return $this->initializeQueueFromFilePath();
    }

    private function initializeQueueFromFilePath(): Mail\Queue\MailQueue
    {
        /** @var Traversable<Mail\Queue\MailQueueItem> $iterator */
        $iterator = new Mail\Iterator\FileIterator(new DirectoryIterator($this->path));

        return new Mail\Queue\MailQueue(
            fn() => $iterator,
        );
    }
}
