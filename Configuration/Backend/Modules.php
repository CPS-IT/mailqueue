<?php

defined('TYPO3') or die();

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

use CPSIT\Typo3Mailqueue\Controller;

return [
    'admin_mailqueue' => [
        'parent' => 'system',
        'position' => ['bottom'],
        'access' => 'admin',
        'workspaces' => 'live',
        'iconIdentifier' => 'tx-mailqueue-module',
        'labels' => 'LLL:EXT:mailqueue/Resources/Private/Language/locallang_mod_mailqueue.xlf',
        'routes' => [
            '_default' => [
                'target' => Controller\MailqueueModuleController::class,
            ],
        ],
    ],
];
