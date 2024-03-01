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

namespace CPSIT\Typo3Mailqueue\ViewHelpers\Format;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

use function sprintf;

use TYPO3\CMS\Core;
use TYPO3Fluid\Fluid;

use function vsprintf;

/**
 * HumanDateViewHelper
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class HumanDateViewHelper extends Fluid\Core\ViewHelper\AbstractViewHelper
{
    use Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

    private static ?Core\Localization\LanguageService $languageService = null;

    public function initializeArguments(): void
    {
        $this->registerArgument('date', DateTimeInterface::class, 'The date to format');
    }

    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        Fluid\Core\Rendering\RenderingContextInterface $renderingContext,
    ): ?string {
        $date = $renderChildrenClosure();

        if (!($date instanceof DateTimeInterface)) {
            return null;
        }

        $now = new DateTimeImmutable();
        $delta = $now->getTimestamp() - $date->getTimestamp();
        $interval = $now->diff($date);

        return self::renderHumanDateInterval($date, $delta, $interval);
    }

    private static function renderHumanDateInterval(DateTimeInterface $date, int $delta, DateInterval $interval): string
    {
        if ($delta <= 30) {
            return self::translate('humanDate.now');
        }
        if ($delta <= 60) {
            return self::translate('humanDate.seconds');
        }
        if ($delta <= 60 * 60) {
            return self::formatInterval($interval->i, 'minute');
        }
        if ($delta <= 60 * 60 * 24) {
            return self::formatInterval($interval->h, 'hour');
        }
        if ($delta <= 60 * 60 * 24 * 7) {
            return self::formatInterval($interval->d, 'day');
        }

        return self::formatDate($date);
    }

    private static function formatInterval(int $interval, string $unit): string
    {
        if ($interval !== 1) {
            $unit .= 's';
        }

        return self::translate(
            sprintf('humanDate.%s', $unit),
            [$interval],
        );
    }

    private static function formatDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * @param list<scalar> $arguments
     */
    private static function translate(string $key, array $arguments = []): string
    {
        self::$languageService ??= Core\Utility\GeneralUtility::makeInstance(Core\Localization\LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);

        return vsprintf(
            self::$languageService->sL(
                sprintf('LLL:EXT:mailqueue/Resources/Private/Language/locallang.xlf:%s', $key),
            ),
            $arguments,
        );
    }
}
