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

return [
    'directories' => [
        '.build',
        '.git',
        '.github',
        'bin',
        'build',
        'public',
        'tailor-version-upload',
        'tests',
        'vendor',
    ],
    'files' => [
        'DS_Store',
        'CODE_OF_CONDUCT.md',
        'composer.lock',
        'CONTRIBUTING.md',
        'crowdin.yaml',
        'editorconfig',
        'gitattributes',
        'gitignore',
        'packaging_exclude.php',
        'phpunit.functional.xml',
        'phpunit.unit.xml',
        'renovate.json',
        'version-bumper.yaml',
    ],
];
