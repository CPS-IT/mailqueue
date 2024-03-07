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
use Symfony\Component\Mailer;
use Symfony\Component\Mime;
use TYPO3\CMS\Core;

/**
 * QueueableTransport
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
interface QueueableTransport extends Core\Mail\DelayedTransportInterface
{
    public function getMailQueue(): Mail\Queue\MailQueue;

    /**
     * @throws Mailer\Exception\TransportExceptionInterface
     */
    public function enqueue(Mime\RawMessage $message, ?Mailer\Envelope $envelope = null): ?Mail\Queue\MailQueueItem;

    /**
     * @throws Mailer\Exception\TransportExceptionInterface
     */
    public function dequeue(Mail\Queue\MailQueueItem $item, Mailer\Transport\TransportInterface $transport): bool;

    public function delete(Mail\Queue\MailQueueItem $item): bool;
}
