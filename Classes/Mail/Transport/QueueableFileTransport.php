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

use CPSIT\Typo3Mailqueue\Enums;
use CPSIT\Typo3Mailqueue\Exception;
use CPSIT\Typo3Mailqueue\Iterator;
use CPSIT\Typo3Mailqueue\Mail;
use DateTimeImmutable;
use DirectoryIterator;
use Generator;
use Psr\Log;
use SplFileInfo;
use Symfony\Component\Mailer;
use Symfony\Component\Mime;
use Symfony\Contracts\EventDispatcher;
use TYPO3\CMS\Core;

/**
 * QueueableFileTransport
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class QueueableFileTransport extends Core\Mail\FileSpool implements RecoverableTransport
{
    private const FILE_SUFFIX_QUEUED = '.message';
    private const FILE_SUFFIX_SENDING = '.message.sending';
    private const FILE_SUFFIX_FAILURE_DATA = '.message.failure';

    private readonly Core\Context\Context $context;

    public function __construct(
        string $path,
        ?EventDispatcher\EventDispatcherInterface $dispatcher = null,
        ?Log\LoggerInterface $logger = null,
    ) {
        parent::__construct($path, $dispatcher, $logger);
        $this->context = Core\Utility\GeneralUtility::makeInstance(Core\Context\Context::class);
    }

    public function recover(int $timeout = 900): void
    {
        $iterator = new DirectoryIterator($this->path);

        // Remove failure metadata
        foreach ($iterator as $file) {
            $path = (string)$file->getRealPath();

            if (str_ends_with($path, self::FILE_SUFFIX_FAILURE_DATA)) {
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
        $directoryIterator = new DirectoryIterator($this->path);
        $execTime = $this->context->getPropertyFromAspect('date', 'timestamp');
        $time = time();
        $count = 0;

        foreach ($directoryIterator as $file) {
            $path = (string)$file->getRealPath();

            if (!str_ends_with($path, self::FILE_SUFFIX_QUEUED)) {
                continue;
            }

            $item = $this->restoreItem($file);

            if ($this->dequeue($item, $transport)) {
                $count++;
            } else {
                // This message has just been caught by another process
                continue;
            }

            if ($this->getMessageLimit() && $count >= $this->getMessageLimit()) {
                break;
            }

            if ($this->getTimeLimit() && ($execTime - $time) >= $this->getTimeLimit()) {
                break;
            }
        }

        return $count;
    }

    public function enqueue(Mime\RawMessage $message, ?Mailer\Envelope $envelope = null): ?Mail\Queue\MailQueueItem
    {
        $sentMessage = $this->send($message, $envelope);

        // Early return if message was rejected
        if ($sentMessage === null) {
            return null;
        }

        // Look up mail in queue
        foreach ($this->getMailQueue() as $mailQueueItem) {
            // Loose comparison is intended
            if ($mailQueueItem->message == $sentMessage) {
                return $mailQueueItem;
            }
        }

        return null;
    }

    public function dequeue(Mail\Queue\MailQueueItem $item, Mailer\Transport\TransportInterface $transport): bool
    {
        $path = $this->path . DIRECTORY_SEPARATOR . $item->id;
        $sendingPath = $this->getFileVariant($path, self::FILE_SUFFIX_SENDING);
        $failurePath = $this->getFileVariant($path, self::FILE_SUFFIX_FAILURE_DATA);

        // We try a rename, it's an atomic operation, and avoid locking the file
        if ($path !== $sendingPath && !rename($path, $sendingPath)) {
            return false;
        }

        try {
            $transport->send($item->message->getMessage(), $item->message->getEnvelope());
        } catch (Mailer\Exception\TransportExceptionInterface $exception) {
            $this->flagFailedTransport($sendingPath, $exception);

            throw $exception;
        }

        // Remove message from queue
        unlink($sendingPath);

        // Remove failure metadata
        if (file_exists($failurePath)) {
            unlink($failurePath);
        }

        return true;
    }

    public function getMailQueue(): Mail\Queue\MailQueue
    {
        return new Mail\Queue\MailQueue(
            $this->initializeQueueFromFilePath(...),
        );
    }

    private function flagFailedTransport(string $file, Mailer\Exception\TransportExceptionInterface $exception): void
    {
        $failure = Mail\TransportFailure::fromException($exception);
        $failurePath = $this->getFileVariant($file, self::FILE_SUFFIX_FAILURE_DATA);

        file_put_contents($failurePath, serialize($failure));
    }

    /**
     * @return Generator<Mail\Queue\MailQueueItem>
     */
    private function initializeQueueFromFilePath(): Generator
    {
        $iterator = new Iterator\LimitedFileIterator(
            new DirectoryIterator($this->path),
            [
                self::FILE_SUFFIX_QUEUED,
                self::FILE_SUFFIX_SENDING,
            ],
        );

        foreach ($iterator as $file) {
            yield $this->restoreItem($file);
        }
    }

    /**
     * @throws Exception\SerializedMessageIsInvalid
     */
    private function restoreItem(SplFileInfo $file): Mail\Queue\MailQueueItem
    {
        $path = (string)$file->getRealPath();
        $lastChanged = $file->getMTime();

        // Unserialize message
        $message = unserialize((string)file_get_contents($path), [
            'allowedClasses' => [
                Mime\RawMessage::class,
                Mime\Message::class,
                Mime\Email::class,
                Mailer\DelayedEnvelope::class,
                Mailer\Envelope::class,
            ],
        ]);

        if (!($message instanceof Mailer\SentMessage)) {
            throw new Exception\SerializedMessageIsInvalid($path);
        }

        // Define mail state
        if (str_ends_with($path, self::FILE_SUFFIX_SENDING)) {
            $state = Enums\MailState::Sending;
            $failure = $this->findFailureMetadata($path);
        } else {
            $state = Enums\MailState::Queued;
            $failure = null;
        }

        // Enforce failure if failure metadata were found
        if ($failure !== null) {
            $state = Enums\MailState::Failed;
        }

        // Add last modification date
        if ($lastChanged !== false) {
            $date = new DateTimeImmutable('@' . $lastChanged);
        } else {
            $date = null;
        }

        return new Mail\Queue\MailQueueItem($file->getFilename(), $message, $state, $date, $failure);
    }

    private function findFailureMetadata(string $file): ?Mail\TransportFailure
    {
        $failurePath = $this->getFileVariant($file, self::FILE_SUFFIX_FAILURE_DATA);

        try {
            return Mail\TransportFailure::fromFile($failurePath);
        } catch (Exception\FileDoesNotExist|Exception\SerializedFailureMetadataIsInvalid) {
            return null;
        }
    }

    private function getFileVariant(string $file, string $suffix): string
    {
        $variants = array_diff(
            [
                self::FILE_SUFFIX_FAILURE_DATA,
                self::FILE_SUFFIX_QUEUED,
                self::FILE_SUFFIX_SENDING,
            ],
            [$suffix],
        );

        foreach ($variants as $variant) {
            if (str_ends_with($file, $variant)) {
                return substr_replace($file, $suffix, -mb_strlen($variant));
            }
        }

        return $file;
    }
}
