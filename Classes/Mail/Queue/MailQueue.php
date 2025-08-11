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

namespace CPSIT\Typo3Mailqueue\Mail\Queue;

/**
 * MailQueue
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 *
 * @implements \IteratorAggregate<MailQueueItem>
 */
final class MailQueue implements \Countable, \IteratorAggregate
{
    /**
     * @param callable(): \Traversable<MailQueueItem> $readQueueFn
     */
    public function __construct(
        private $readQueueFn,
    ) {}

    /**
     * @return list<MailQueueItem>
     */
    public function get(): array
    {
        return array_values(iterator_to_array($this));
    }

    public function count(): int
    {
        $queue = ($this->readQueueFn)();

        if (is_countable($queue)) {
            return count($queue);
        }

        return count(iterator_to_array($queue));
    }

    /**
     * @return \ArrayIterator<int, MailQueueItem>
     */
    public function getIterator(): \ArrayIterator
    {
        $iterator = ($this->readQueueFn)();
        $queue = iterator_to_array($iterator);

        usort($queue, self::orderQueueItems(...));

        return new \ArrayIterator($queue);
    }

    private static function orderQueueItems(MailQueueItem $a, MailQueueItem $b): int
    {
        if ($a->date < $b->date) {
            return -1;
        }

        if ($a->date > $b->date) {
            return 1;
        }

        return 0;
    }
}
