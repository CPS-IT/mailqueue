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

namespace CPSIT\Typo3Mailqueue\Mail\Queue;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * MailQueue
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 *
 * @implements IteratorAggregate<MailQueueItem>
 */
final class MailQueue implements Countable, IteratorAggregate
{
    /**
     * @param callable(): Traversable<MailQueueItem> $readQueueFn
     */
    public function __construct(
        private $readQueueFn,
    ) {}

    /**
     * @return list<MailQueueItem>
     */
    public function get(): array
    {
        $queue = iterator_to_array($this);

        usort($queue, self::orderQueueItems(...));

        return $queue;
    }

    public function count(): int
    {
        $queue = $this->getIterator();

        if (is_countable($queue)) {
            return count($queue);
        }

        return count(iterator_to_array($queue));
    }

    public function getIterator(): Traversable
    {
        return ($this->readQueueFn)();
    }

    private static function orderQueueItems(MailQueueItem $a, MailQueueItem $b): int
    {
        return $a->date?->getTimestamp() <=> $b->date?->getTimestamp();
    }
}
