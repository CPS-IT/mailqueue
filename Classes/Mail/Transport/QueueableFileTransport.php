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

use CPSIT\Typo3Mailqueue\Mail;
use DirectoryIterator;
use Symfony\Component\Mailer;
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

        // Recover failed transports
        foreach ($iterator as $file) {
            $path = (string)$file->getRealPath();

            if (str_ends_with($path, '.message.failed')) {
                rename($path, substr($path, 0, -7));
            }
        }

        // Recover stuck transports
        parent::recover($timeout);
    }

    /**
     * @throws Mailer\Exception\TransportExceptionInterface
     */
    public function flushQueue(Mailer\Transport\TransportInterface $transport): int
    {
        try {
            return parent::flushQueue($transport);
        } catch (Mailer\Exception\TransportExceptionInterface $exception) {
            $this->flagFailedTransports();

            throw $exception;
        }
    }

    public function getMailQueue(): Mail\Queue\MailQueue
    {
        return $this->initializeQueueFromFilePath();
    }

    private function flagFailedTransports(): void
    {
        $iterator = new DirectoryIterator($this->path);
        $count = 0;

        foreach ($iterator as $file) {
            $path = (string)$file->getRealPath();

            if (str_ends_with($path, '.message.sending')) {
                rename($path, substr($path, 0, -8) . '.failed');

                $count++;
            }

            // Don't run further validations for files that haven't been touched
            if ($this->getMessageLimit() > 0 && $count >= $this->getMessageLimit()) {
                break;
            }
        }
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
