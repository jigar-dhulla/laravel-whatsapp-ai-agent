<?php

namespace JigarDhulla\LaravelWhatsApp\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
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

    protected function setUpWacliDatabase(): void
    {
        config()->set('database.connections.'.WhatsAppMessageReader::CONNECTION_NAME, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => false,
        ]);

        Schema::connection(WhatsAppMessageReader::CONNECTION_NAME)->create('messages', function (Blueprint $table) {
            $table->bigIncrements('rowid');
            $table->string('chat_jid');
            $table->string('chat_name')->nullable();
            $table->string('msg_id');
            $table->string('sender_jid')->nullable();
            $table->string('sender_name')->nullable();
            $table->bigInteger('ts');
            $table->boolean('from_me');
            $table->text('text')->nullable();
            $table->text('display_text')->nullable();
            $table->string('media_type')->nullable();
        });
    }
}
