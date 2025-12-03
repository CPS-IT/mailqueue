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

namespace CPSIT\Typo3Mailqueue\Controller;

use CPSIT\Typo3Mailqueue\Configuration;
use CPSIT\Typo3Mailqueue\Enums;
use CPSIT\Typo3Mailqueue\Exception;
use CPSIT\Typo3Mailqueue\Mail;
use CPSIT\Typo3Mailqueue\Traits;
use Psr\Http\Message;
use Symfony\Component\Mailer;
use TYPO3\CMS\Backend;
use TYPO3\CMS\Core;

/**
 * MailqueueModuleController
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
#[Backend\Attribute\AsController]
final class MailqueueModuleController
{
    use Traits\TranslatableTrait;
    use Core\Http\AllowedMethodsTrait;

    public function __construct(
        private readonly Configuration\ExtensionConfiguration $extensionConfiguration,
        private readonly Core\Imaging\IconFactory $iconFactory,
        private readonly Backend\Template\ModuleTemplateFactory $moduleTemplateFactory,
        private readonly Core\Mail\Mailer $mailer,
        private readonly Backend\Routing\UriBuilder $uriBuilder,
        private readonly Core\Context\Context $context,
    ) {}

    public function __invoke(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $page = $this->resolvePageIdFromRequest($request);

        // Force redirect when page selector was used
        if ($request->getMethod() === 'POST' && !isset($request->getQueryParams()['page'])) {
            return new Core\Http\RedirectResponse(
                $this->uriBuilder->buildUriFromRoute('system_mailqueue', ['page' => $page]),
            );
        }

        $this->assertAllowedHttpMethod($request, 'GET');

        $template = $this->moduleTemplateFactory->create($request);
        $transport = $this->mailer->getTransport();
        /** @var string|null $sendId */
        $sendId = $request->getQueryParams()['send'] ?? null;
        /** @var string|null $deleteId */
        $deleteId = $request->getQueryParams()['delete'] ?? null;

        if ($transport instanceof Mail\Transport\QueueableTransport) {
            $templateVariables = $this->resolveTemplateVariables($transport, $page, $sendId, $deleteId);
        } else {
            $templateVariables = [
                'unsupportedTransport' => $this->getTransportFromMailConfiguration(),
            ];
        }

        if (Core\Utility\ExtensionManagementUtility::isLoaded('lowlevel')) {
            $this->addLinkToConfigurationModule($template);
        }

        return $template
            ->assignMultiple($templateVariables)
            ->renderResponse('List')
        ;
    }

    private function resolvePageIdFromRequest(Message\ServerRequestInterface $request): int
    {
        $pageId = $request->getQueryParams()['page'] ?? null;

        if (\is_numeric($pageId)) {
            return (int)$pageId;
        }

        if (!\is_array($request->getParsedBody())) {
            return 1;
        }

        $pageId = $request->getParsedBody()['page'] ?? 1;

        if (\is_numeric($pageId)) {
            return (int)$pageId;
        }

        return 1;
    }

    /**
     * @return array{
     *     delayThreshold: positive-int,
     *     deleteResult: bool,
     *     failing: bool,
     *     longestPendingInterval: non-negative-int,
     *     pagination: Core\Pagination\SimplePagination,
     *     paginator: Core\Pagination\ArrayPaginator,
     *     queue: list<Mail\Queue\MailQueueItem>,
     *     sendResult: Enums\MailState|null,
     * }
     */
    private function resolveTemplateVariables(
        Mail\Transport\QueueableTransport $transport,
        int $currentPageNumber = 1,
        ?string $sendId = null,
        ?string $deleteId = null,
    ): array {
        $failing = false;
        $longestPendingInterval = 0;
        /** @var int $now */
        $now = $this->context->getPropertyFromAspect('date', 'timestamp');
        $sendResult = null;
        $deleteResult = false;

        if (is_string($sendId)) {
            $sendResult = $this->sendMail($transport, $sendId);
        }

        if (is_string($deleteId)) {
            $deleteResult = $this->deleteMail($transport, $deleteId);
        }

        foreach ($transport->getMailQueue() as $mailQueueItem) {
            if ($mailQueueItem->date !== null) {
                $longestPendingInterval = max($longestPendingInterval, $now - $mailQueueItem->date->getTimestamp());
            }

            if ($mailQueueItem->state === Enums\MailState::Failed) {
                $failing = true;
            }
        }

        $queue = $transport->getMailQueue()->get();
        $paginator = new Core\Pagination\ArrayPaginator(
            $queue,
            $currentPageNumber,
            $this->extensionConfiguration->getItemsPerPage(),
        );
        $pagination = new Core\Pagination\SimplePagination($paginator);

        return [
            'delayThreshold' => $this->extensionConfiguration->getQueueDelayThreshold(),
            'deleteResult' => $deleteResult,
            'failing' => $failing,
            'longestPendingInterval' => $longestPendingInterval,
            'pagination' => $pagination,
            'paginator' => $paginator,
            'queue' => $queue,
            'sendResult' => $sendResult,
        ];
    }

    private function sendMail(Mail\Transport\QueueableTransport $transport, string $queueItemId): Enums\MailState
    {
        $mailQueueItem = $this->getMailById($transport, $queueItemId);

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

    private function deleteMail(Mail\Transport\QueueableTransport $transport, string $queueItemId): bool
    {
        $mailQueueItem = $this->getMailById($transport, $queueItemId);

        if ($mailQueueItem === null) {
            return false;
        }

        return $transport->delete($mailQueueItem);
    }

    private function getMailById(
        Mail\Transport\QueueableTransport $transport,
        string $queueItemId,
    ): ?Mail\Queue\MailQueueItem {
        foreach ($transport->getMailQueue() as $mailQueueItem) {
            if ($mailQueueItem->id === $queueItemId) {
                return $mailQueueItem;
            }
        }

        return null;
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
     * @throws Exception\MailTransportIsNotConfigured
     */
    private function getTransportFromMailConfiguration(): string
    {
        $mailConfiguration = $GLOBALS['TYPO3_CONF_VARS']['MAIL'] ?? null;

        if (!\is_array($mailConfiguration)) {
            throw new Exception\MailTransportIsNotConfigured();
        }

        $spoolType = $mailConfiguration['transport_spool_type'] ?? null;

        if (is_string($spoolType) && trim($spoolType) !== '') {
            return $spoolType;
        }

        $transport = $mailConfiguration['transport'] ?? null;

        if (is_string($transport) && trim($transport) !== '') {
            return $transport;
        }

        throw new Exception\MailTransportIsNotConfigured();
    }
}
