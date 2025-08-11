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

namespace CPSIT\Typo3Mailqueue\Mail\Queue;

use CPSIT\Typo3Mailqueue\Enums;
use CPSIT\Typo3Mailqueue\Mail;
use Symfony\Component\Mailer;

/**
 * MailQueueItem
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class MailQueueItem
{
    public function __construct(
        public readonly string $id,
        public readonly Mailer\SentMessage $message,
        public readonly Enums\MailState $state,
        public readonly ?\DateTimeInterface $date = null,
        public readonly ?Mail\TransportFailure $failure = null,
    ) {}
}
