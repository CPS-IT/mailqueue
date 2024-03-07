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

namespace CPSIT\Typo3Mailqueue\Iterator;

use FilterIterator;
use Iterator;
use SplFileInfo;
use Traversable;

/**
 * LimitedFileIterator
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 *
 * @extends FilterIterator<int, SplFileInfo, Traversable<SplFileInfo>>
 */
final class LimitedFileIterator extends FilterIterator
{
    /**
     * @param list<string> $acceptedSuffixes
     */
    public function __construct(
        Iterator $iterator,
        private readonly array $acceptedSuffixes,
    ) {
        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        $file = $this->getInnerIterator()->current();
        $path = $file->getRealPath();

        foreach ($this->acceptedSuffixes as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
