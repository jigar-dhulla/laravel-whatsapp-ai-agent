<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Tests\Feature;

use LaravelWhatsApp\Agents\WhatsAppAgent;
use LaravelWhatsApp\Tests\TestCase;

class WhatsAppAgentTest extends TestCase
{
    public function test_it_returns_hardcoded_default_instructions(): void
    {
        $this->assertStringContainsString('WhatsApp', (new WhatsAppAgent)->instructions());
    }

    public function test_it_can_be_prompted_via_laravel_ai(): void
    {
        WhatsAppAgent::fake(['Sure thing!']);

        $response = (new WhatsAppAgent)->prompt('Hello');

        $this->assertSame('Sure thing!', $response->text);
        WhatsAppAgent::assertPrompted('Hello');
    }
}
