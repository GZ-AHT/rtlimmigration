<?php

namespace App\Support\Chat\AutoReply;

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

    /** @return array{enabled: bool, assistant_name: string, fallback: string, rules: list<array{id: string, name: string, keywords: list<string>, reply: string, active: bool}>} */
    public function forAdmin(): array
    {
        return ['enabled' => $this->enabled(), 'assistant_name' => $this->assistantName(), 'fallback' => $this->fallback(), 'rules' => $this->rules()];
    }

    /** @param  array{enabled: bool, assistant_name: string, fallback: string, rules: list<array<string, mixed>>}  $data validated */
    public function save(array $data): void
    {
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
