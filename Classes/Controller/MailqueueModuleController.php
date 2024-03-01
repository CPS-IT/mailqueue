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

namespace CPSIT\Typo3Mailqueue\Controller;

use CPSIT\Typo3Mailqueue\Enums\MailState;
use CPSIT\Typo3Mailqueue\Mail;

use function max;

use Psr\Http\Message;
use TYPO3\CMS\Backend;
use TYPO3\CMS\Core;

/**
 * MailqueueModuleController
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class MailqueueModuleController
{
    public function __construct(
        private readonly Backend\Template\ModuleTemplateFactory $moduleTemplateFactory,
        private readonly Core\Mail\Mailer $mailer,
    ) {}

    public function __invoke(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $template = $this->moduleTemplateFactory->create($request);
        $transport = $this->mailer->getTransport();

        if ($transport instanceof Mail\Transport\QueueableTransport) {
            $template->assignMultiple($this->resolveTemplateVariables($transport));
        } else {
            $template->assign('unsupportedTransport', $transport::class);
        }

        return $template->renderResponse('List');
    }

    /**
     * @return array{
     *     failing: bool,
     *     longestPendingInterval: non-negative-int,
     *     transport: Mail\Transport\QueueableTransport,
     * }
     */
    private function resolveTemplateVariables(Mail\Transport\QueueableTransport $transport): array
    {
        $failing = false;
        $longestPendingInterval = 0;
        $now = time();

        foreach ($transport->getMailQueue() as $mailQueueItem) {
            if ($mailQueueItem->date !== null) {
                $longestPendingInterval = max($longestPendingInterval, $now - $mailQueueItem->date->getTimestamp());
            }

            if ($mailQueueItem->state === MailState::Failed) {
                $failing = true;
            }
        }

        return [
            'failing' => $failing,
            'longestPendingInterval' => $longestPendingInterval,
            'transport' => $transport,
        ];
    }
}
