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

namespace CPSIT\Typo3Mailqueue;

use TYPO3\CMS\Core;

/**
 * Extension
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class Extension
{
    public const KEY = 'mailqueue';

    /**
     * Register custom XClasses.
     *
     * FOR USE IN ext_localconf.php ONLY.
     */
    public static function registerXClasses(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Core\Mail\FileSpool::class] = [
            'className' => Mail\Transport\QueueableFileTransport::class,
        ];
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Core\Mail\MemorySpool::class] = [
            'className' => Mail\Transport\QueueableMemoryTransport::class,
        ];
    }

    /**
     * Register custom backend modules.
     *
     * FOR USE IN ext_tables.php ONLY.
     *
     * @todo Remove once support for TYPO3 v11 is dropped
     */
    public static function registerBackendModules(): void
    {
        // Backend module configuration in TYPO3 >= v12 is done in Configuration/Backend/Modules.php
        if ((new Core\Information\Typo3Version())->getMajorVersion() >= 12) {
            return;
        }

        Core\Utility\ExtensionManagementUtility::addModule(
            'system',
            'mailqueue',
            'bottom',
            null,
            [
                'routeTarget' => \CPSIT\Typo3Mailqueue\Controller\MailqueueModuleController::class,
                'access' => 'admin',
                'id' => 'system_mailqueue',
                'name' => 'system_mailqueue',
                'iconIdentifier' => 'tx-mailqueue-module',
                'labels' => 'LLL:EXT:mailqueue/Resources/Private/Language/locallang_mod_mailqueue.xlf',
            ],
        );
    }
}
