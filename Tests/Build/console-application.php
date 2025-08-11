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

use CPSIT\Typo3Mailqueue\Command;
use Symfony\Component\Console;
use Symfony\Component\Mailer;
use TYPO3\CMS\Core;

require dirname(__DIR__, 2) . '/.Build/vendor/autoload.php';

$mailer = new Core\Mail\Mailer(
    new Mailer\Transport\NullTransport(),
);

$flushCommand = new Command\FlushQueueCommand($mailer);
$flushCommand->setName('mailqueue:flushqueue');

$listCommand = new Command\ListQueueCommand($mailer);
$listCommand->setName('mailqueue:listqueue');

$application = new Console\Application();
$application->add($flushCommand);
$application->add($listCommand);

return $application;
