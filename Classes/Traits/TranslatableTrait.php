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

namespace CPSIT\Typo3Mailqueue\Traits;

use TYPO3\CMS\Core;

/**
 * TranslatableTrait
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
trait TranslatableTrait
{
    private static ?Core\Localization\LanguageService $languageService = null;

    /**
     * @param list<scalar> $arguments
     */
    protected static function translate(string $key, array $arguments = []): string
    {
        /** @var Core\Authentication\BackendUserAuthentication $backendUser */
        $backendUser = $GLOBALS['BE_USER'];

        self::$languageService ??= Core\Utility\GeneralUtility::makeInstance(Core\Localization\LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);

        return vsprintf(
            self::$languageService->sL(
                sprintf('LLL:EXT:mailqueue/Resources/Private/Language/locallang.xlf:%s', $key),
            ),
            $arguments,
        );
    }
}
