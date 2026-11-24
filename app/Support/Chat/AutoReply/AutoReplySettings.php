<?php

namespace App\Support\Chat\AutoReply;

use App\Models\Media;
use App\Support\Settings\SettingsRepository;

/**
 * Automatic website-chat replies, kept in the settings store under the
 * "chat_auto_reply" group and managed at Live Chat → Auto-replies.
 * Until an admin saves, the built-in starter replies are used.
 *
 * @phpstan-type Rule array{id: string, name: string, keywords: list<string>, reply: string, active: bool}
 */
class AutoReplySettings
{
    public const MAX_RULES = 50;

    public const MAX_KEYWORDS = 30;

    public const MAX_REPLY = 1000;

    public const DEFAULT_FALLBACK = 'Thank you for your message! Our support team will get back to you shortly.';

    public const DEFAULT_NAME = 'RTL Assistant';

    public const DEFAULT_WELCOME = 'Hello! Welcome to {site_name}. How can we help you today?';

    public const DEFAULT_AGENT = 'MD Hasnat';

    public const DEFAULT_HANDOVER = 'You are now connected with {agent_name}. How can I assist you?';

    public const DEFAULT_HANDOVER_KEYWORDS = [
        'human', 'real person', 'live agent', 'live chat agent', 'support agent', 'customer service', 'representative',
        'talk to a person', 'speak to a person', 'talk to someone', 'speak to someone', 'talk to a human', 'speak to a human',
        'talk to an adviser', 'speak to an adviser', 'talk to adviser', 'talk to an advisor', 'speak to an advisor',
        'talk to a counselor', 'speak to a counselor', 'talk to your team',
    ];

    public function __construct(private SettingsRepository $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('chat_auto_reply.enabled', true);
    }

    public function fallback(): string
    {
        $value = trim((string) $this->settings->get('chat_auto_reply.fallback', self::DEFAULT_FALLBACK));

        return $value;
    }

    public function assistantName(): string
    {
        $name = trim((string) $this->settings->get('chat_auto_reply.assistant_name', self::DEFAULT_NAME));

        return $name !== '' ? $name : self::DEFAULT_NAME;
    }

    /** The greeting a new chat opens with (chats now start from a name and phone number, without a first message). */
    public function welcome(): string
    {
        return trim((string) $this->settings->get('chat_auto_reply.welcome', self::DEFAULT_WELCOME));
    }

    public function handoverEnabled(): bool
    {
        return (bool) $this->settings->get('chat_auto_reply.handover_enabled', true);
    }

    public function handoverAgentName(): string
    {
        $name = trim((string) $this->settings->get('chat_auto_reply.handover_agent_name', self::DEFAULT_AGENT));

        return $name !== '' ? $name : self::DEFAULT_AGENT;
    }

    /** The staff member the visitor is "connected with": their profile picture and assignment. */
    public function handoverAgentId(): ?int
    {
        $id = $this->settings->get('chat_auto_reply.handover_agent_id');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    public function handoverAvatarId(): ?int
    {
        $id = $this->settings->get('chat_auto_reply.handover_avatar');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    public function handoverMessage(): string
    {
        $text = trim((string) $this->settings->get('chat_auto_reply.handover_message', self::DEFAULT_HANDOVER));

        return $text !== '' ? $text : self::DEFAULT_HANDOVER;
    }

    /** @return list<string> */
    public function handoverKeywords(): array
    {
        $stored = $this->settings->get('chat_auto_reply.handover_keywords');

        return is_array($stored) ? array_values(array_filter(array_map('strval', $stored))) : self::DEFAULT_HANDOVER_KEYWORDS;
    }

    /** @return list<array{id: string, name: string, keywords: list<string>, reply: string, active: bool}> */
    public function rules(): array
    {
        $stored = $this->settings->get('chat_auto_reply.rules');

        if (! is_array($stored)) {
            return self::starterRules();
        }

        $rules = [];

        foreach ($stored as $rule) {
            if (is_array($rule) && is_string($rule['reply'] ?? null)) {
                $rules[] = [
                    'id' => (string) ($rule['id'] ?? ''),
                    'name' => (string) ($rule['name'] ?? ''),
                    'keywords' => array_values(array_filter(array_map('strval', (array) ($rule['keywords'] ?? [])))),
                    'reply' => $rule['reply'],
                    'active' => (bool) ($rule['active'] ?? true),
                ];
            }
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function forAdmin(): array
    {
        $avatar = $this->handoverAvatarId();

        return [
            'enabled' => $this->enabled(), 'assistant_name' => $this->assistantName(), 'fallback' => $this->fallback(), 'rules' => $this->rules(),
            'welcome' => $this->welcome(),
            'handover_enabled' => $this->handoverEnabled(),
            'handover_agent_name' => $this->handoverAgentName(),
            'handover_agent_id' => $this->handoverAgentId(),
            'handover_avatar' => $avatar ? Media::query()->find($avatar)?->present() : null,
            'handover_message' => $this->handoverMessage(),
            'handover_keywords' => $this->handoverKeywords(),
        ];
    }

    /** @param  array<string, mixed>  $data validated */
    public function save(array $data): void
    {
        $this->settings->set('chat_auto_reply.welcome', trim((string) $data['welcome']));
        $this->settings->set('chat_auto_reply.handover_enabled', (bool) $data['handover_enabled']);
        $this->settings->set('chat_auto_reply.handover_agent_name', trim((string) $data['handover_agent_name']));
        $this->settings->set('chat_auto_reply.handover_agent_id', $data['handover_agent_id'] ?? null);
        $this->settings->set('chat_auto_reply.handover_avatar', $data['handover_avatar'] ?? null);
        $this->settings->set('chat_auto_reply.handover_message', trim((string) $data['handover_message']));
        $this->settings->set('chat_auto_reply.handover_keywords', array_values(array_unique(array_filter(array_map(fn ($k) => mb_strtolower(trim((string) $k)), (array) ($data['handover_keywords'] ?? []))))));

        $this->settings->set('chat_auto_reply.enabled', (bool) $data['enabled']);
        $this->settings->set('chat_auto_reply.assistant_name', trim($data['assistant_name']));
        $this->settings->set('chat_auto_reply.fallback', trim($data['fallback']));
        $this->settings->set('chat_auto_reply.rules', array_map(fn (array $r) => [
            'id' => (string) ($r['id'] ?: bin2hex(random_bytes(6))),
            'name' => trim((string) $r['name']),
            'keywords' => array_values(array_unique(array_filter(array_map(fn ($k) => mb_strtolower(trim((string) $k)), (array) ($r['keywords'] ?? []))))),
            'reply' => trim((string) $r['reply']),
            'active' => (bool) $r['active'],
        ], $data['rules']));
    }

    /** @return list<array{id: string, name: string, keywords: list<string>, reply: string, active: bool}> */
    public static function starterRules(): array
    {
        return [
            ['id' => 'greeting', 'name' => 'Greeting', 'active' => true,
                'keywords' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening', 'assalamu alaikum', 'salam'],
                'reply' => 'Hello! Welcome to {site_name}. How can we help you today?'],
            ['id' => 'services', 'name' => 'Services', 'active' => true,
                'keywords' => ['service', 'services', 'your services', 'what are your services', 'what services', 'what do you do', 'what do you offer', 'help with', 'consultancy', 'consultation'],
                'reply' => 'We provide immigration, visa processing, and related consultancy services.'],
            ['id' => 'contact', 'name' => 'Contact', 'active' => true,
                'keywords' => ['contact', 'how can i contact you', 'phone', 'call', 'email', 'address', 'office', 'location', 'reach you', 'talk to someone'],
                'reply' => 'You can contact our support team for assistance.'],
            ['id' => 'visa', 'name' => 'Visa', 'active' => true,
                'keywords' => ['visa', 'student visa', 'tourist visa', 'work permit', 'immigration'],
                'reply' => 'Our advisers help with visa applications, documents and the whole process. Tell us the country you are interested in and we will guide you.'],
            ['id' => 'study', 'name' => 'Study abroad', 'active' => true,
                'keywords' => ['study', 'study abroad', 'university', 'admission', 'course', 'scholarship', 'ielts'],
                'reply' => 'We help students choose a country, a university and a course, and with the admission process. What would you like to study, and where?'],
            ['id' => 'thanks', 'name' => 'Thanks', 'active' => true,
                'keywords' => ['thanks', 'thank you', 'thx', 'dhonnobad'],
                'reply' => 'You are welcome! Is there anything else we can help with?'],
        ];
    }
}
