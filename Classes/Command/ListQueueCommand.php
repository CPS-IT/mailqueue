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

namespace CPSIT\Typo3Mailqueue\Command;

use CPSIT\Typo3Mailqueue\Enums;
use CPSIT\Typo3Mailqueue\Mail;
use Symfony\Component\Console;
use Symfony\Component\Mailer;
use Symfony\Component\Mime;
use TYPO3\CMS\Core;

/**
 * ListQueueCommand
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class ListQueueCommand extends Console\Command\Command
{
    private const WAIT_INTERVAL = 5;

    private Console\Style\SymfonyStyle $io;

    public function __construct(
        private readonly Core\Mail\Mailer $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'strict',
            's',
            Console\Input\InputOption::VALUE_NONE,
            'Exit with non-successful status code in case any mail transports are failing',
        );
        $this->addOption(
            'watch',
            'w',
            Console\Input\InputOption::VALUE_NONE,
            sprintf(
                'Watch for changes within mail queue, e.g. if mails are sent (refreshes all %d seconds)',
                self::WAIT_INTERVAL,
            ),
        );
    }

    protected function initialize(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): void
    {
        $this->io = new Console\Style\SymfonyStyle($input, $output);
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        $strict = $input->getOption('strict');
        $watch = $input->getOption('watch');

        $transport = $this->mailer->getTransport();
        $hasFailures = false;

        // Early return if unsupported mail transport is configured
        if (!($transport instanceof Mail\Transport\QueueableTransport)) {
            $this->io->error(
                sprintf(
                    'The configured mail transport "%s" is not supported. Please configure a mail spooler as mail transport.',
                    $transport::class,
                ),
            );

            return self::INVALID;
        }

        if ($watch) {
            // Early return if --watch option is used on an unsupported output
            if (!($output instanceof Console\Output\ConsoleOutput)) {
                $this->io->error('The --watch option can only be used for console outputs.');

                return self::FAILURE;
            }

            $section = $output->section();

            $this->io = new Console\Style\SymfonyStyle($input, $section);

            $this->watchForQueueUpdates($section, $transport);
        } else {
            $hasFailures = $this->decorateMailQueue($transport->getMailQueue());
        }

        if ($strict && $hasFailures) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function watchForQueueUpdates(
        Console\Output\ConsoleSectionOutput $section,
        Mail\Transport\QueueableTransport $transport,
    ): never {
        while (true) {
            $section->clear();

            $this->decorateMailQueue($transport->getMailQueue());
            $this->io->writeln(
                sprintf(
                    '<fg=gray>List is refreshed every %d seconds. Exit with Ctrl+C.</>',
                    self::WAIT_INTERVAL,
                ),
            );

            sleep(self::WAIT_INTERVAL);
        }
    }

    private function decorateMailQueue(Mail\Queue\MailQueue $mailQueue): bool
    {
        $numberOfMailsInQueue = count($mailQueue);

        if ($numberOfMailsInQueue === 0) {
            $this->io->success('No mails are currently in queue.');

            return false;
        }

        $this->io->section(
            sprintf('Total mails in queue: %d', $numberOfMailsInQueue),
        );

        if ($this->io->isVerbose()) {
            return $this->renderAsTable($mailQueue);
        }

        return $this->renderAsList($mailQueue);
    }

    private function renderAsTable(Mail\Queue\MailQueue $mailQueue): bool
    {
        $hasDate = false;
        $hasFailures = false;

        $headers = ['State', 'Date', 'Subject', 'Recipient(s)', 'Sender'];
        $rows = [];

        /** @var Mail\Queue\MailQueueItem $mailQueueItem */
        foreach ($mailQueue as $mailQueueItem) {
            if ($mailQueueItem->date !== null) {
                $hasDate = true;
            }

            $rows[] = [
                $this->decorateState($mailQueueItem->state),
                $mailQueueItem->date?->format('Y-m-d H:i:s') ?? '<fg=gray>Unknown</>',
                $this->getMailSubject($mailQueueItem->message),
                $this->getMailRecipients($mailQueueItem->message),
                $mailQueueItem->message->getEnvelope()->getSender()->getAddress(),
            ];

            if ($mailQueueItem->state === Enums\MailState::Failed) {
                $hasFailures = true;
            }
        }

        if (!$hasDate) {
            unset($headers[1]);

            foreach (array_keys($rows) as $number) {
                unset($rows[$number][1]);
            }
        }

        $this->io->createTable()
            ->setStyle('default')
            ->setHeaders($headers)
            ->setRows($rows)
            ->render()
        ;

        $this->io->newLine();

        return $hasFailures;
    }

    private function renderAsList(Mail\Queue\MailQueue $mailQueue): bool
    {
        $hasFailures = false;

        /** @var Mail\Queue\MailQueueItem $mailQueueItem */
        foreach ($mailQueue as $mailQueueItem) {
            if ($mailQueueItem->date !== null) {
                $this->io->writeln(
                    sprintf(
                        '%s <fg=gray>%s</> <options=bold>%s</> (to %s)',
                        $this->decorateState($mailQueueItem->state),
                        $mailQueueItem->date->format('Y-m-d H:i:s'),
                        $this->getMailSubject($mailQueueItem->message, 30),
                        $this->getMailRecipients($mailQueueItem->message),
                    ),
                );
            } else {
                $this->io->writeln(
                    sprintf(
                        '%s <options=bold>%s</> (to %s)',
                        $this->decorateState($mailQueueItem->state),
                        $this->getMailSubject($mailQueueItem->message, 50),
                        $this->getMailRecipients($mailQueueItem->message),
                    ),
                );
            }

            if ($mailQueueItem->state === Enums\MailState::Failed) {
                $hasFailures = true;
            }
        }

        $this->io->newLine();

        return $hasFailures;
    }

    private function decorateState(Enums\MailState $state): string
    {
        return match ($state) {
            Enums\MailState::Failed => '<bg=red;fg=black> FAIL </>',
            Enums\MailState::Queued => '<bg=blue;fg=black> WAIT </>',
            Enums\MailState::Sending => '<bg=yellow;fg=black> SEND </>',
            Enums\MailState::AlreadySent, Enums\MailState::Sent => '<bg=green;fg=black> SENT </>',
        };
    }

    private function getMailSubject(Mailer\SentMessage $message, int $crop = 0): ?string
    {
        $subject = null;

        if ($message->getMessage() instanceof Mime\Email) {
            $subject = $message->getMessage()->getSubject();
        }

        if ($subject === null && $message->getOriginalMessage() instanceof Mime\Email) {
            $subject = $message->getOriginalMessage()->getSubject();
        }

        if ($subject === null) {
            return null;
        }

        if ($crop > 0 && mb_strlen($subject) > $crop) {
            return mb_substr($subject, 0, $crop) . '…';
        }

        return $subject;
    }

    private function getMailRecipients(Mailer\SentMessage $message): string
    {
        $allRecipients = $message->getEnvelope()->getRecipients();

        if ($allRecipients === []) {
            return '<fg=gray>None</>';
        }

        $address = array_shift($allRecipients)->getAddress();
        $recipients = sprintf('<href=mailto:%1$s>%1$s</>', $address);

        if ($allRecipients !== []) {
            $recipients .= sprintf(' +%d more', count($allRecipients));
        }

        return $recipients;
    }
}
