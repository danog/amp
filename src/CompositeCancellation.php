<?php declare(strict_types=1);

namespace Amp;

use Amp\Internal\CompositeCancellationState;
use Revolt\EventLoop;

/**
 * A cancellation that is requested when any of the given cancellations is requested.
 *
 * @implements Cancellation[read-props|write-props|io]
 *
 * @api
 */
final class CompositeCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly CompositeCancellationState $state;

    public function __construct(Cancellation ...$cancellations)
    {
        // The callback keeps this state object, not the cancellation, so destroying the cancellation can
        // unsubscribe and break the cycle between the state and the child cancellations.
        $state = new CompositeCancellationState();
        $this->state = $state;

        $onCancel = static function (CancelledException $exception) use ($state): void {
            if ($state->exception) {
                return;
            }

            $state->exception = $exception;

            foreach ($state->cancellations as [$cancellation, $id]) {
                $cancellation->unsubscribe($id);
            }

            $state->cancellations = [];

            foreach ($state->callbacks as $callback) {
                EventLoop::queue($callback, $exception);
            }

            $state->callbacks = [];
        };

        foreach ($cancellations as $cancellation) {
            $id = $cancellation->subscribe($onCancel);
            $state->cancellations[] = [$cancellation, $id];
        }
    }

    public function __destruct()
    {
        foreach ($this->state->cancellations as [$cancellation, $id]) {
            $cancellation->unsubscribe($id);
        }

        $this->state->cancellations = [];
    }

    /**
     * @psalm-capabilities read-props|write-props|io
     */
    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        $id = $this->state->nextId;
        $this->state->nextId = \str_increment($this->state->nextId);

        if ($this->state->exception) {
            EventLoop::queue($callback, $this->state->exception);
        } else {
            $this->state->callbacks[$id] = $callback;
        }

        return $id;
    }

    /**
     * @psalm-capabilities read-props|write-props
     */
    #[\Override]
    public function unsubscribe(string $id): void
    {
        unset($this->state->callbacks[$id]);
    }

    /** @psalm-capabilities read-props */
    #[\Override]
    public function isRequested(): bool
    {
        if ($this->state->exception) {
            return true;
        }

        // Check each cancellation directly, as the callback setting the exception is invoked asynchronously.
        foreach ($this->state->cancellations as [$cancellation]) {
            if ($cancellation->isRequested()) {
                return true;
            }
        }

        return false;
    }

    /** @psalm-capabilities read-props|write-props */
    #[\Override]
    public function throwIfRequested(): void
    {
        if ($this->state->exception) {
            throw $this->state->exception;
        }

        // Check each cancellation directly, as the callback setting the exception is invoked asynchronously.
        foreach ($this->state->cancellations as [$cancellation]) {
            $cancellation->throwIfRequested();
        }
    }
}
