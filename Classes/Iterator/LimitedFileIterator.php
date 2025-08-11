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

namespace CPSIT\Typo3Mailqueue\Iterator;

/**
 * LimitedFileIterator
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 *
 * @extends \FilterIterator<int, \SplFileInfo, \Traversable<\SplFileInfo>>
 */
final class LimitedFileIterator extends \FilterIterator
{
    /**
     * @param list<string> $acceptedSuffixes
     */
    public function __construct(
        \Iterator $iterator,
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
