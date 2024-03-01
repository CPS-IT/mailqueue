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
use CPSIT\Typo3Mailqueue\Traits;
use Psr\Http\Message;
use Symfony\Component\Mailer;
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
    use Traits\TranslatableTrait;

    private readonly Core\Information\Typo3Version $typo3Version;

    public function __construct(
        private readonly Core\Imaging\IconFactory $iconFactory,
        private readonly Backend\Template\ModuleTemplateFactory $moduleTemplateFactory,
        private readonly Core\Mail\Mailer $mailer,
        private readonly Backend\Routing\UriBuilder $uriBuilder,
    ) {
        $this->typo3Version = new Core\Information\Typo3Version();
    }

    public function __invoke(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $template = $this->moduleTemplateFactory->create($request);
        $transport = $this->mailer->getTransport();
        $sendId = $request->getQueryParams()['send'] ?? null;
        $templateVariables = [];

        if ($transport instanceof Mail\Transport\QueueableTransport) {
            $templateVariables = $this->resolveTemplateVariables($transport, $sendId);
        } else {
            $templateVariables['unsupportedTransport'] = $transport::class;
        }

        // @todo Remove once support for TYPO3 v11 is dropped
        $templateVariables['layout'] = $this->typo3Version->getMajorVersion() < 12 ? 'Default' : 'Module';

        if (Core\Utility\ExtensionManagementUtility::isLoaded('lowlevel')) {
            $this->addLinkToConfigurationModule($template);
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
     *     longestPendingInterval: non-negative-int,
     *     sendResult: Enums\MailState|null,
     *     transport: Mail\Transport\QueueableTransport,
     * }
     */
    private function resolveTemplateVariables(
        Mail\Transport\QueueableTransport $transport,
        string $sendId = null,
    ): array {
        $failing = false;
        $longestPendingInterval = 0;
        $now = time();
        $sendResult = null;

        if (is_string($sendId)) {
            $sendResult = $this->sendMail($transport, $sendId);
        }

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
            'longestPendingInterval' => $longestPendingInterval,
            'sendResult' => $sendResult,
            'transport' => $transport,
        ];
    }

    private function sendMail(Mail\Transport\QueueableTransport $transport, string $queueItemId): Enums\MailState
    {
        $mailQueueItem = null;

        foreach ($transport->getMailQueue() as $item) {
            if ($item->id === $queueItemId) {
                $mailQueueItem = $item;
                break;
            }
        }

        if ($mailQueueItem === null) {
            return Enums\MailState::AlreadySent;
        }

        try {
            $transport->dequeue($mailQueueItem, $this->mailer->getRealTransport());
        } catch (Mailer\Exception\TransportExceptionInterface) {
            return Enums\MailState::Failed;
        }

        return Enums\MailState::Sent;
    }

    private function addLinkToConfigurationModule(Backend\Template\ModuleTemplate $template): void
    {
        $buttonBar = $template->getDocHeaderComponent()->getButtonBar();

        $button = $buttonBar->makeLinkButton();
        $button->setHref(
            (string)$this->uriBuilder->buildUriFromRoute(
                'system_config',
                ['node' => ['MAIL' => 1], 'tree' => 'confVars'],
            )
        );
        $button->setIcon($this->iconFactory->getIcon('actions-cog', Core\Imaging\Icon::SIZE_SMALL));
        $button->setTitle(self::translate('button.config'));
        $button->setShowLabelText(true);

        $buttonBar->addButton($button);
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
