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

namespace CPSIT\Typo3Mailqueue\Mail\Iterator;

use CPSIT\Typo3Mailqueue\Enums;
use CPSIT\Typo3Mailqueue\Exception;
use CPSIT\Typo3Mailqueue\Mail;
use DateTimeImmutable;
use FilterIterator;
use SplFileInfo;
use Symfony\Component\Mailer;
use Symfony\Component\Mime;
use Traversable;

/**
 * FileIterator
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 *
 * @template-extends FilterIterator<int, SplFileInfo|Mail\Queue\MailQueueItem, Traversable<SplFileInfo>>
 */
final class FileIterator extends FilterIterator
{
    private const SUFFIX_FAILED = '.message.failed';
    private const SUFFIX_QUEUED = '.message';
    private const SUFFIX_SENDING = '.message.sending';

    /**
     * @throws Exception\SerializedMessageIsInvalid
     */
    public function current(): Mail\Queue\MailQueueItem
    {
        /** @var SplFileInfo $file */
        $file = parent::current();
        $path = $file->getRealPath();
        $lastChanged = $file->getMTime();

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

        if (str_ends_with($path, self::SUFFIX_SENDING)) {
            $state = Enums\MailState::Sending;
        } elseif (str_ends_with($path, self::SUFFIX_FAILED)) {
            $state = Enums\MailState::Failed;
        } else {
            $state = Enums\MailState::Queued;
        }

        if ($lastChanged !== false) {
            $date = new DateTimeImmutable('@' . $lastChanged);
        } else {
            $date = null;
        }

        return new Mail\Queue\MailQueueItem($message, $state, $date);
    }

    public function accept(): bool
    {
        $file = $this->getInnerIterator()->current();

        if (!($file instanceof SplFileInfo)) {
            return false;
        }

        $path = $file->getRealPath();

        return str_ends_with($path, self::SUFFIX_FAILED)
            || str_ends_with($path, self::SUFFIX_QUEUED)
            || str_ends_with($path, self::SUFFIX_SENDING)
        ;
    }
}
