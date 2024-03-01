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

namespace CPSIT\Typo3Mailqueue\Mail\Iterator;

use CPSIT\Typo3Mailqueue\Mail;
use FilterIterator;
use SplFileInfo;
use Traversable;

/**
 * FileIterator
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 *
 * @template-extends FilterIterator<int, SplFileInfo, Traversable<SplFileInfo>>
 */
final class FileIterator extends FilterIterator
{
    public function accept(): bool
    {
        $file = $this->getInnerIterator()->current();
        $path = $file->getRealPath();

        return str_ends_with($path, Mail\Transport\QueueableFileTransport::FILE_SUFFIX_QUEUED)
            || str_ends_with($path, Mail\Transport\QueueableFileTransport::FILE_SUFFIX_SENDING)
        ;
    }
}
