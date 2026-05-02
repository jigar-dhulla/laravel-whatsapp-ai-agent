<?php

declare(strict_types=1);

namespace LaravelWhatsApp;

use Illuminate\Support\ServiceProvider;
use LaravelWhatsApp\Console\Commands\ListenCommand;
use LaravelWhatsApp\Console\Commands\SetupCommand;
use LaravelWhatsApp\Console\Commands\StatusCommand;
use LaravelWhatsApp\Services\Wacli;
use LaravelWhatsApp\Services\WhatsAppMessageReader;

class WhatsAppAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/whatsapp-agent.php', 'whatsapp-agent');

        $this->app->singleton(Wacli::class);
        $this->app->singleton(WhatsAppMessageReader::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/whatsapp-agent.php' => config_path('whatsapp-agent.php'),
        ], 'whatsapp-agent-config');

        $this->commands([
            ListenCommand::class,
            SetupCommand::class,
            StatusCommand::class,
        ]);

        $db = config('whatsapp-agent.wacli.database');

        if ($db) {
            config(['database.connections.'.WhatsAppMessageReader::CONNECTION_NAME => [
                'driver' => 'sqlite',
                'database' => $db,
                'foreign_key_constraints' => false,
            ]]);
        }
    }
}
