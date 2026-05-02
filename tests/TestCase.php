<?php

namespace LaravelWhatsApp\Tests;

use Laravel\Ai\AiServiceProvider;
use LaravelWhatsApp\WhatsAppAgentServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            WhatsAppAgentServiceProvider::class,
        ];
    }
}
