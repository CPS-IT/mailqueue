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
use TYPO3\CMS\Core;

/**
 * ExtensionConfiguration
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class ExtensionConfiguration
{
    private const DEFAULT_DELAY_THRESHOLD = 1800;
    private const DEFAULT_ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly Core\Configuration\ExtensionConfiguration $configuration,
    ) {}

    /**
     * @return positive-int
     */
    public function getQueueDelayThreshold(): int
    {
        try {
            $delayThreshold = $this->configuration->get(Extension::KEY, 'queue/delayThreshold');
        } catch (Core\Exception) {
            return self::DEFAULT_DELAY_THRESHOLD;
        }

        if (!is_scalar($delayThreshold)) {
            return self::DEFAULT_DELAY_THRESHOLD;
        }

        return max(1, (int)$delayThreshold);
    }

    /**
     * @return positive-int
     */
    public function getItemsPerPage(): int
    {
        try {
            $itemsPerPage = $this->configuration->get(Extension::KEY, 'pagination/itemsPerPage');
        } catch (Core\Exception) {
            return self::DEFAULT_ITEMS_PER_PAGE;
        }

        if (!is_scalar($itemsPerPage)) {
            return self::DEFAULT_ITEMS_PER_PAGE;
        }

        return max(1, (int)$itemsPerPage);
    }
}
