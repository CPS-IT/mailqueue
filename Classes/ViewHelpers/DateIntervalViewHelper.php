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

namespace CPSIT\Typo3Mailqueue\ViewHelpers;

use Closure;
use DateTimeInterface;
use TYPO3Fluid\Fluid;

/**
 * DateIntervalViewHelper
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class DateIntervalViewHelper extends Fluid\Core\ViewHelper\AbstractViewHelper
{
    use Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

    public function initializeArguments(): void
    {
        $this->registerArgument('date', DateTimeInterface::class, 'The date for which to create an interval');
    }

    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        Fluid\Core\Rendering\RenderingContextInterface $renderingContext,
    ): ?int {
        $date = $renderChildrenClosure();

        if (!($date instanceof DateTimeInterface)) {
            return null;
        }

        return time() - $date->getTimestamp();
    }
}
