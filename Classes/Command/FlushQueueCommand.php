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

namespace CPSIT\Typo3Mailqueue\Command;

use CPSIT\Typo3Mailqueue\Exception;
use CPSIT\Typo3Mailqueue\Mail;
use Symfony\Component\Console;
use Symfony\Component\Mailer;
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
        $this->addOption(
            'recover-timeout',
            'r',
            Console\Input\InputOption::VALUE_REQUIRED,
            'Timeout in seconds for recovering mails that have taken too long to send',
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
        $recoverTimeout = $input->getOption('recover-timeout');
        $transport = $this->mailer->getTransport();

        try {
            // Early return if unsupported mail transport is configured
            if (!($transport instanceof Mail\Transport\QueueableTransport)) {
                throw new Exception\CommandFailureException(
                    sprintf(
                        'The configured mail transport "%s" is not supported. Please configure a mail spooler as mail transport.',
                        $transport::class,
                    ),
                    self::INVALID,
                );
            }

            $mailQueue = $transport->getMailQueue()->get();
            $numberOfMailsInQueue = count($mailQueue);

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
                throw new Exception\CommandFailureException(
                    'Limit must be a number greater than or equal to 1.',
                    self::INVALID,
                );
            }

            $this->recover($recoverTimeout, $transport);
            $this->flushQueue($limit, $mailQueue, $transport);
        } catch (Exception\CommandFailureException $exception) {
            $this->io->error($exception->getMessage());
            $this->writeJsonResult([
                'error' => $exception->getMessage(),
            ]);

            return $exception->statusCode;
        }

        return self::SUCCESS;
    }

    /**
     * @throws Exception\CommandFailureException
     */
    private function recover(?string $recoverTimeout, Mail\Transport\QueueableTransport $transport): void
    {
        if ($recoverTimeout !== null) {
            $recoverTimeout = (int)$recoverTimeout;

            // Show warning if non-recoverable transport is used with recover timeout
            if (!($transport instanceof Mail\Transport\RecoverableTransport)) {
                $this->io->warning('You passed --recover-timeout to a non-recoverable mail transport.');
            }

            // Early return if invalid recover timeout is provided
            if ($recoverTimeout < 1) {
                throw new Exception\CommandFailureException(
                    'Recover timeout must be a number greater than or equal to 1.',
                    self::INVALID,
                );
            }
        }

        // Recover mails that have taken too long to send
        if ($transport instanceof Mail\Transport\RecoverableTransport) {
            if ($recoverTimeout !== null) {
                $transport->recover($recoverTimeout);
            } else {
                $transport->recover();
            }
        }
    }

    /**
     * @param list<Mail\Queue\MailQueueItem> $mailQueueItems
     * @throws Exception\CommandFailureException
     */
    private function flushQueue(int $limit, array $mailQueueItems, Mail\Transport\QueueableTransport $transport): void
    {
        $this->io->section('Flushing mail queue');

        $numberOfMailsInQueue = count($mailQueueItems);
        $realTransport = $this->mailer->getRealTransport();
        $progressBar = $this->io->createProgressBar($limit);

        // Send mails from queue
        foreach ($progressBar->iterate($mailQueueItems, $limit) as $i => $item) {
            if ($i >= $limit) {
                $progressBar->finish();
                break;
            }

            try {
                $transport->dequeue($item, $realTransport);
            } catch (Mailer\Exception\TransportExceptionInterface $exception) {
                $this->io->newLine(2);

                throw new Exception\CommandFailureException($exception->getMessage(), self::FAILURE);
            }

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
