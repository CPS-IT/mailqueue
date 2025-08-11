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
