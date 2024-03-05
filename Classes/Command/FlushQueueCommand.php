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

use CPSIT\Typo3Mailqueue\Mail;
use Symfony\Component\Console;
use TYPO3\CMS\Core;

/**
 * FlushQueueCommand
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class FlushQueueCommand extends Console\Command\Command
{
    private ?Console\Output\OutputInterface $jsonOutput = null;
    private Console\Style\SymfonyStyle $io;

    public function __construct(
        private readonly Core\Mail\Mailer $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'json',
            'j',
            Console\Input\InputOption::VALUE_NONE,
            'Output results in JSON format',
        );
        $this->addOption(
            'limit',
            'l',
            Console\Input\InputOption::VALUE_REQUIRED,
            'Maximum number of mails to send in one iteration',
        );
    }

    protected function initialize(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): void
    {
        if ($input->getOption('json')) {
            $this->jsonOutput = $output;

            if ($output instanceof Console\Output\ConsoleOutputInterface) {
                $output = $output->getErrorOutput();
            } else {
                $output = new Console\Output\NullOutput();
            }
        }

        $this->io = new Console\Style\SymfonyStyle($input, $output);
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        $limit = $input->getOption('limit');
        $transport = $this->mailer->getTransport();

        // Early return if unsupported mail transport is configured
        if (!($transport instanceof Mail\Transport\QueueableTransport)) {
            $message = sprintf(
                'The configured mail transport "%s" is not supported. Please configure a mail spooler as mail transport.',
                $transport::class,
            );

            $this->io->error($message);
            $this->writeJsonResult([
                'error' => $message,
            ]);

            return self::INVALID;
        }

        $mailQueue = $transport->getMailQueue();
        $numberOfMailsInQueue = count($mailQueue);
        $realTransport = $this->mailer->getRealTransport();

        // Early return if mail queue is empty
        if ($numberOfMailsInQueue === 0) {
            $message = 'No mails are currently in queue.';

            $this->io->success($message);
            $this->writeJsonResult([
                'result' => $message,
            ]);

            return self::SUCCESS;
        }

        // Limit number of sent mails from command option or send all mails
        if ($limit !== null) {
            $limit = (int)$limit;
        } else {
            $limit = $numberOfMailsInQueue;
        }

        // Early return if invalid limit is provided
        if ($limit < 1) {
            $message = 'Limit must be a number greater than or equal to 1.';

            $this->io->error($message);
            $this->writeJsonResult([
                'error' => $message,
            ]);

            return self::INVALID;
        }

        $this->io->section('Flushing mail queue');

        $progressBar = $this->io->createProgressBar($limit);

        // Send mails from queue
        foreach ($progressBar->iterate($mailQueue, $limit) as $i => $item) {
            if ($i >= $limit) {
                $progressBar->finish();
                break;
            }

            $transport->dequeue($item, $realTransport);

            --$numberOfMailsInQueue;
        }

        $this->io->newLine(2);

        if ($numberOfMailsInQueue > 0) {
            $this->io->success(
                sprintf(
                    'Successfully sent %d mail%s, %d mail%s still enqueued.',
                    $limit,
                    $limit === 1 ? '' : 's',
                    $numberOfMailsInQueue,
                    $numberOfMailsInQueue === 1 ? ' is' : 's are',
                ),
            );
        } else {
            $this->io->success(
                sprintf(
                    'Successfully flushed mail queue (sent %d mail%s).',
                    $limit,
                    $limit === 1 ? '' : 's',
                ),
            );
        }

        $this->writeJsonResult([
            'sent' => $limit,
            'remaining' => $numberOfMailsInQueue,
        ]);

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function writeJsonResult(array $json): void
    {
        if ($this->jsonOutput === null) {
            return;
        }

        $this->jsonOutput->writeln(
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }
}
