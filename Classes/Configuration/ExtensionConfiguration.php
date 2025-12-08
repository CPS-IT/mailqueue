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

namespace CPSIT\Typo3Mailqueue\Configuration;

use CPSIT\Typo3Mailqueue\Extension;
use mteu\TypedExtConf;

/**
 * ExtensionConfiguration
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
#[TypedExtConf\Attribute\ExtensionConfig(Extension::KEY)]
final readonly class ExtensionConfiguration
{
    /**
     * @param positive-int $queueDelayThreshold
     * @param positive-int $itemsPerPage
     */
    public function __construct(
        #[TypedExtConf\Attribute\ExtConfProperty('queue.delayThreshold')]
        public int $queueDelayThreshold = 1800,
        #[TypedExtConf\Attribute\ExtConfProperty('pagination.itemsPerPage')]
        public int $itemsPerPage = 20,
    ) {}
}
