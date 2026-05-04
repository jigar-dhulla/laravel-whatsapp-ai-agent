<?php

namespace JigarDhulla\LaravelWhatsApp\Tests;

use JigarDhulla\LaravelWhatsApp\WhatsAppAgentServiceProvider;
use Laravel\Ai\AiServiceProvider;
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
