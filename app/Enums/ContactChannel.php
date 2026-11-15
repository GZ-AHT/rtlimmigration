<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/** How a lead was contacted (REQ §7 "Contact History"), or prefers to be (Figma contact form). */
enum ContactChannel: string
{
    use HasOptions;

    case Phone = 'phone';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Video = 'video';
    case InPerson = 'in_person';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone call',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
            self::Video => 'Video call',
            self::InPerson => 'In person',
            self::Sms => 'SMS',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Phone => 'call',
            self::Email => 'mail',
            self::WhatsApp => 'chat',
            self::Video => 'videocam',
            self::InPerson => 'groups',
            self::Sms => 'sms',
        };
    }
}
