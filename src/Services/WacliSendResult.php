<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Services;

/**
 * Outcome of a wacli send attempt. Replaces the previous [bool, string, string]
 * tuple so callers read named properties instead of positional indexes.
 */
readonly class WacliSendResult
{
    public function __construct(
        public bool $successful,
        public string $output,
        public string $errorOutput,
    ) {}
}
