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

namespace CPSIT\Typo3Mailqueue\Tests\Functional\Command;

use CPSIT\Typo3Mailqueue as Src;
use PHPUnit\Framework;
use Symfony\Component\Console;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * ListQueueCommandTest
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\ListQueueCommand::class)]
final class ListQueueCommandTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'mailqueue',
        'typed_extconf',
    ];

    protected bool $initializeDatabase = false;

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport_spool_filepath' => 'EXT:mailqueue/Tests/Functional/Fixtures/Messages',
            'transport_spool_type' => 'file',
        ],
    ];

    private Console\Tester\CommandTester $commandTester;

    public function setUp(): void
    {
        parent::setUp();

        $this->commandTester = $this->createCommandTester();
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfUnsupportedTransportIsConfigured(): void
    {
        // Reset MAIL configuration
        /**
         * @var array<string, mixed> $mailSettings
         * @phpstan-ignore offsetAccess.nonOffsetAccessible
         */
        $mailSettings = $GLOBALS['TYPO3_CONF_VARS']['MAIL'];
        unset($mailSettings['transport_spool_type']);

        $commandTester = $this->createCommandTester($mailSettings);

        $commandTester->execute([]);

        self::assertSame(Console\Command\Command::INVALID, $commandTester->getStatusCode());
        self::assertMatchesRegularExpression(
            '/The configured mail transport "[^"]+" is not supported\./',
            $commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeFailsOnFailuresInStrictMode(): void
    {
        $this->commandTester->execute([
            '--strict' => true,
        ]);

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
    }

    #[Framework\Attributes\Test]
    public function executeListsMailsInQueue(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertMatchesRegularExpression('/FAIL[^(]+\(to test-3-bar@example.com\)/', $output);
        self::assertMatchesRegularExpression('/WAIT[^(]+\(to test-2-bar@example.com\)/', $output);
        self::assertMatchesRegularExpression('/SEND[^(]+\(to test-1-bar@example.com\)/', $output);
    }

    /**
     * @param array<string, mixed>|null $mailSettings
     */
    private function createCommandTester(?array $mailSettings = null): Console\Tester\CommandTester
    {
        if ($mailSettings !== null) {
            $transportFactory = $this->get(Core\Mail\TransportFactory::class);
            $transport = $transportFactory->get($mailSettings);
            $mailer = new Core\Mail\Mailer($transport);
        } else {
            $mailer = $this->get(Core\Mail\Mailer::class);
        }

        $command = new Src\Command\ListQueueCommand($mailer);

        return new Console\Tester\CommandTester($command);
    }
}
