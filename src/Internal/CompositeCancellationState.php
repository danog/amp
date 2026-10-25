<?php declare(strict_types=1);

namespace Amp\Internal;

use Amp\Cancellation;
use Amp\CancelledException;

/**
 * Mutable state of a {@see \Amp\CompositeCancellation}, shared with the callback subscribed to the
 * child cancellations so that callback does not retain the cancellation object.
 *
 * The resulting cycle (state → child cancellations → callback → state) is broken when the
 * cancellation is destroyed and unsubscribes.
 *
 * @internal
 */
final class CompositeCancellationState
{
    /** @var array<int, array{Cancellation, string}> */
    public array $cancellations = [];

    /** @var array<string, \Closure(CancelledException): void> */
    public array $callbacks = [];

    public string $nextId = 'a';

    public ?CancelledException $exception = null;
}
