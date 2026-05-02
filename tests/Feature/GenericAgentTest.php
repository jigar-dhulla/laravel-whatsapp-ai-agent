<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Tests\Feature;

use LaravelWhatsApp\Agents\GenericAgent;
use LaravelWhatsApp\Tests\TestCase;

class GenericAgentTest extends TestCase
{
    public function test_it_returns_hardcoded_default_instructions(): void
    {
        $this->assertStringContainsString('WhatsApp', (new GenericAgent)->instructions());
    }

    public function test_it_can_be_prompted_via_laravel_ai(): void
    {
        GenericAgent::fake(['Sure thing!']);

        $response = (new GenericAgent)->prompt('Hello');

        $this->assertSame('Sure thing!', $response->text);
        GenericAgent::assertPrompted('Hello');
    }
}
