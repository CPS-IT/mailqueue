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

use CPSIT\Typo3Mailqueue\Enums;
use CPSIT\Typo3Mailqueue\Mail;
use Psr\Http\Message;
use TYPO3\CMS\Backend;
use TYPO3\CMS\Core;
use TYPO3\CMS\Fluid;

/**
 * MailqueueModuleController
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class MailqueueModuleController
{
    private readonly Core\Information\Typo3Version $typo3Version;

    public function __construct(
        private readonly Backend\Template\ModuleTemplateFactory $moduleTemplateFactory,
        private readonly Core\Mail\Mailer $mailer,
    ) {
        $this->typo3Version = new Core\Information\Typo3Version();
    }

    public function __invoke(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $template = $this->moduleTemplateFactory->create($request);
        $transport = $this->mailer->getTransport();
        $templateVariables = [];

        if ($transport instanceof Mail\Transport\QueueableTransport) {
            $templateVariables = $this->resolveTemplateVariables($transport);
        } else {
            $templateVariables['unsupportedTransport'] = $transport::class;
        }

        // @todo Remove once support for TYPO3 v11 is dropped
        if ($this->typo3Version->getMajorVersion() < 12) {
            return $this->renderLegacyTemplate($template, $templateVariables);
        }

        return $template
            ->assignMultiple($templateVariables)
            ->renderResponse('List')
        ;
    }

    /**
     * @return array{
     *     failing: bool,
     *     layout: string,
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

            if ($mailQueueItem->state === Enums\MailState::Failed) {
                $failing = true;
            }
        }

        return [
            'failing' => $failing,
            // @todo Remove once support for TYPO3 v11 is dropped
            'layout' => $this->typo3Version->getMajorVersion() < 12 ? 'Default' : 'Module',
            'longestPendingInterval' => $longestPendingInterval,
            'transport' => $transport,
        ];
    }

    /**
     * @todo Remove once support for TYPO3 v11 is dropped
     *
     * @param array<string, mixed> $templateVariables
     */
    private function renderLegacyTemplate(
        Backend\Template\ModuleTemplate $template,
        array $templateVariables,
    ): Core\Http\HtmlResponse {
        $view = Core\Utility\GeneralUtility::makeInstance(Fluid\View\StandaloneView::class);
        $view->setTemplateRootPaths(['EXT:mailqueue/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:mailqueue/Resources/Private/Partials/']);
        $view->setLayoutRootPaths(['EXT:mailqueue/Resources/Private/Layouts/']);
        $view->setTemplate('List');
        $view->assignMultiple($templateVariables);

        $template->setContent($view->render());

        return new Core\Http\HtmlResponse($template->renderContent());
    }
}
