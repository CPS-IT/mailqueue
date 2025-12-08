<?php

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

/** @noinspection PhpUndefinedVariableInspection */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Mailqueue',
    'description' => 'Extension to improve TYPO3\'s mail spooler with additional components',
    'category' => 'be',
    'version' => '0.5.0',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'author' => 'Elias Häußler',
    'author_email' => 'e.haeussler@familie-redlich.de',
    'author_company' => 'coding. powerful. systems. CPS GmbH',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.25-13.4.99',
            'php' => '8.2.0-8.5.99',
            'typed_extconf' => '0.3.0-1.99.99',
        ],
    ],
];
