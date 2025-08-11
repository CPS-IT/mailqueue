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

namespace CPSIT\Typo3Mailqueue\Exception;

/**
 * MailTransportIsNotConfigured
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class MailTransportIsNotConfigured extends Exception
{
    public function __construct()
    {
        parent::__construct(
            'No mail transport is configured. Please provide mail configuration in $GLOBALS[\'TYPO3_CONF_VARS\'][\'MAIL\'].',
            1709803814,
        );
    }
}
