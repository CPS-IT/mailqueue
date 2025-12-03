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
}
