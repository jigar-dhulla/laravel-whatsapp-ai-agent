<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp;

use Illuminate\Support\ServiceProvider;
use JigarDhulla\LaravelWhatsApp\Console\Commands\ChatsCommand;
use JigarDhulla\LaravelWhatsApp\Console\Commands\GroupsCommand;
use JigarDhulla\LaravelWhatsApp\Console\Commands\ListenCommand;
use JigarDhulla\LaravelWhatsApp\Console\Commands\SetupCommand;
use JigarDhulla\LaravelWhatsApp\Console\Commands\StatusCommand;
use JigarDhulla\LaravelWhatsApp\Listening\NativeSleeper;
use JigarDhulla\LaravelWhatsApp\Listening\Sleeper;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;

class WhatsAppAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/whatsapp-agent.php', 'whatsapp-agent');

        $this->app->singleton(Wacli::class);
        $this->app->singleton(WhatsAppMessageReader::class);
        $this->app->bind(Sleeper::class, NativeSleeper::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/whatsapp-agent.php' => config_path('whatsapp-agent.php'),
        ], 'whatsapp-agent-config');

        $this->commands([
            ChatsCommand::class,
            GroupsCommand::class,
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
