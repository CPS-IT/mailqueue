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

namespace CPSIT\Typo3Mailqueue\ViewHelpers\Format;

use CPSIT\Typo3Mailqueue\Traits;
use TYPO3Fluid\Fluid;

/**
 * HumanDateViewHelper
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class HumanDateViewHelper extends Fluid\Core\ViewHelper\AbstractViewHelper
{
    use Traits\TranslatableTrait;

    public function initializeArguments(): void
    {
        $this->registerArgument('date', \DateTimeInterface::class, 'The date to format', true);
    }

    public function render(): ?string
    {
        $date = $this->renderChildren();

        if (!($date instanceof \DateTimeInterface)) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $delta = $now->getTimestamp() - $date->getTimestamp();
        $interval = $now->diff($date);

        return self::renderHumanDateInterval($date, $delta, $interval);
    }

    public function getContentArgumentName(): string
    {
        return 'date';
    }

    private static function renderHumanDateInterval(\DateTimeInterface $date, int $delta, \DateInterval $interval): string
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

    private static function formatDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
