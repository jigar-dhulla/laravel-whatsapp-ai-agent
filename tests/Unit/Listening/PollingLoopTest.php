<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Unit\Listening;

use JigarDhulla\LaravelWhatsApp\Listening\NullListenerReporter;
use JigarDhulla\LaravelWhatsApp\Listening\PollingLoop;
use JigarDhulla\LaravelWhatsApp\Listening\Sleeper;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;
use RuntimeException;
use Throwable;

class PollingLoopTest extends TestCase
{
    public function test_it_runs_exactly_max_iterations_and_sleeps_between_them(): void
    {
        $sleeper = $this->recordingSleeper();

        $runs = 0;
        (new PollingLoop($sleeper))->run(
            function () use (&$runs): void {
                $runs++;
            },
            interval: 3,
            maxIterations: 4,
            reporter: new NullListenerReporter,
        );

        $this->assertSame(4, $runs);
        // No sleep after the final iteration.
        $this->assertSame([3, 3, 3], $sleeper->slept);
    }

    public function test_once_semantics_run_a_single_iteration_without_sleeping(): void
    {
        $sleeper = $this->recordingSleeper();

        $runs = 0;
        (new PollingLoop($sleeper))->run(
            function () use (&$runs): void {
                $runs++;
            },
            interval: 5,
            maxIterations: 1,
            reporter: new NullListenerReporter,
        );

        $this->assertSame(1, $runs);
        $this->assertSame([], $sleeper->slept);
    }

    public function test_a_failing_iteration_is_reported_and_does_not_break_the_loop(): void
    {
        $sleeper = $this->recordingSleeper();

        $reporter = new class extends NullListenerReporter
        {
            public ?Throwable $failure = null;

            public function iterationFailed(Throwable $e): void
            {
                $this->failure = $e;
            }
        };

        $runs = 0;
        (new PollingLoop($sleeper))->run(
            function () use (&$runs): void {
                $runs++;
                if ($runs === 1) {
                    throw new RuntimeException('boom');
                }
            },
            interval: 1,
            maxIterations: 2,
            reporter: $reporter,
        );

        $this->assertSame(2, $runs);
        $this->assertInstanceOf(RuntimeException::class, $reporter->failure);
        $this->assertSame('boom', $reporter->failure->getMessage());
    }

    /**
     * @return Sleeper&object{slept: array<int, int>}
     */
    private function recordingSleeper(): Sleeper
    {
        return new class implements Sleeper
        {
            /** @var array<int, int> */
            public array $slept = [];

            public function sleep(int $seconds): void
            {
                $this->slept[] = $seconds;
            }
        };
    }
}
