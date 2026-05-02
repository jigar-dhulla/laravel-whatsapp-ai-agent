<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Tests\Unit;

use LaravelWhatsApp\Agents\GenericAgent;
use LaravelWhatsApp\Services\AgentRouter;
use LaravelWhatsApp\Tests\TestCase;

class AgentRouterTest extends TestCase
{
    private function agent(array $overrides = []): array
    {
        return array_merge([
            'agent' => GenericAgent::class,
            'provider' => 'anthropic',
            'model' => 'claude-opus-4-7',
            'triggers' => ['@agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ], $overrides);
    }

    public function test_empty_body_returns_no_matches(): void
    {
        $router = new AgentRouter([$this->agent()]);

        $this->assertSame([], $router->match('111@s.whatsapp.net', null));
        $this->assertSame([], $router->match('111@s.whatsapp.net', ''));
        $this->assertSame([], $router->match('111@s.whatsapp.net', '   '));
    }

    public function test_no_agents_returns_empty(): void
    {
        $router = new AgentRouter([]);

        $this->assertSame([], $router->match('111@s.whatsapp.net', 'hey @agent'));
    }

    public function test_it_matches_when_jid_in_chats_and_trigger_matches(): void
    {
        $router = new AgentRouter([$this->agent()]);

        $matched = $router->match('111@s.whatsapp.net', 'hey @agent please help');

        $this->assertCount(1, $matched);
    }

    public function test_it_does_not_match_when_jid_not_in_scope(): void
    {
        $router = new AgentRouter([$this->agent(['chats' => ['222@s.whatsapp.net']])]);

        $this->assertSame([], $router->match('111@s.whatsapp.net', 'hey @agent'));
    }

    public function test_it_matches_group_jid(): void
    {
        $router = new AgentRouter([$this->agent(['chats' => [], 'groups' => ['group@g.us']])]);

        $matched = $router->match('group@g.us', 'hey @agent');

        $this->assertCount(1, $matched);
    }

    public function test_agent_with_empty_scope_never_matches(): void
    {
        $router = new AgentRouter([$this->agent(['chats' => [], 'groups' => []])]);

        $this->assertSame([], $router->match('111@s.whatsapp.net', 'hey @agent'));
    }

    public function test_it_does_not_match_when_trigger_absent(): void
    {
        $router = new AgentRouter([$this->agent(['triggers' => ['@agent']])]);

        $this->assertSame([], $router->match('111@s.whatsapp.net', 'just chatting'));
    }

    public function test_trigger_matching_is_case_insensitive(): void
    {
        $router = new AgentRouter([$this->agent(['triggers' => ['@agent']])]);

        $this->assertCount(1, $router->match('111@s.whatsapp.net', 'Hey @AGENT please'));
    }

    public function test_empty_triggers_matches_all_messages_in_scope(): void
    {
        $router = new AgentRouter([$this->agent(['triggers' => []])]);

        $this->assertCount(1, $router->match('111@s.whatsapp.net', 'just chatting'));
    }

    public function test_multiple_agents_can_match_same_message(): void
    {
        $agentA = $this->agent(['provider' => 'anthropic']);
        $agentB = $this->agent(['provider' => 'openai']);

        $router = new AgentRouter([$agentA, $agentB]);

        $matched = $router->match('111@s.whatsapp.net', 'hey @agent');

        $this->assertCount(2, $matched);
    }

    public function test_only_matching_agent_is_returned_when_one_fails_scope(): void
    {
        $agentA = $this->agent(['chats' => ['111@s.whatsapp.net']]);
        $agentB = $this->agent(['chats' => ['222@s.whatsapp.net']]);

        $router = new AgentRouter([$agentA, $agentB]);

        $matched = $router->match('111@s.whatsapp.net', 'hey @agent');

        $this->assertCount(1, $matched);
        $this->assertSame('anthropic', $matched[0]['provider']);
    }

    public function test_allowed_jids_returns_deduped_union_of_all_agent_scopes(): void
    {
        $agentA = $this->agent(['chats' => ['aaa@s.whatsapp.net', 'bbb@s.whatsapp.net'], 'groups' => []]);
        $agentB = $this->agent(['chats' => ['bbb@s.whatsapp.net'], 'groups' => ['grp@g.us']]);

        $router = new AgentRouter([$agentA, $agentB]);

        $jids = $router->allowedJids();

        sort($jids);

        $this->assertSame(['aaa@s.whatsapp.net', 'bbb@s.whatsapp.net', 'grp@g.us'], $jids);
    }

    public function test_allowed_jids_is_empty_when_all_agents_have_no_scope(): void
    {
        $router = new AgentRouter([$this->agent(['chats' => [], 'groups' => []])]);

        $this->assertSame([], $router->allowedJids());
    }
}
