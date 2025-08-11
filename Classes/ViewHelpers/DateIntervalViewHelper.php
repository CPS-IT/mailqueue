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

namespace CPSIT\Typo3Mailqueue\ViewHelpers;

use TYPO3\CMS\Core;
use TYPO3Fluid\Fluid;

/**
 * DateIntervalViewHelper
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class DateIntervalViewHelper extends Fluid\Core\ViewHelper\AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('date', \DateTimeInterface::class, 'The date for which to create an interval', true);
    }

    public function render(): ?int
    {
        $date = $this->renderChildren();
        /** @var int $now */
        $now = Core\Utility\GeneralUtility::makeInstance(Core\Context\Context::class)->getPropertyFromAspect('date', 'timestamp');

        if (!($date instanceof \DateTimeInterface)) {
            return null;
        }

        return $now - $date->getTimestamp();
    }

    public function getContentArgumentName(): string
    {
        return 'date';
    }
}
