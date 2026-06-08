<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Listening;

use JigarDhulla\LaravelWhatsApp\Listening\Contracts\ListenerReporter;
use Throwable;

/**
 * Drives the polling cadence: counts iterations, runs the supplied tick,
 * isolates each iteration's failures, and sleeps between rounds. Pass a
 * $maxIterations of null to loop forever, or an integer to stop after N rounds.
 */
class PollingLoop
{
    public function __construct(private readonly Sleeper $sleeper) {}

    public function run(callable $tick, int $interval, ?int $maxIterations, ListenerReporter $reporter): void
    {
        $iterations = 0;

        while ($maxIterations === null || $iterations < $maxIterations) {
            $iterations++;

            $reporter->tick($iterations);

            try {
                $tick();
            } catch (Throwable $e) {
                $reporter->iterationFailed($e);

                report($e);
            }

            if ($maxIterations !== null && $iterations >= $maxIterations) {
                break;
            }

            $this->sleeper->sleep($interval);
        }
    }
}
