<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LeadSource: string
{
    use HasOptions;

    case Website = 'website';
    case Manual = 'manual';
    case Phone = 'phone';
    case WalkIn = 'walk_in';
    case LiveChat = 'live_chat';
    case Referral = 'referral';
    case Partner = 'partner';
    case SocialMedia = 'social_media';
    case SkillAssessment = 'skill_assessment';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website form',
            self::Manual => 'Added by staff',
            self::Phone => 'Phone call',
            self::WalkIn => 'Office walk-in',
            self::LiveChat => 'Live chat',
            self::Referral => 'Referral',
            self::Partner => 'Partner',
            self::SocialMedia => 'Social media',
            self::SkillAssessment => 'Skill migration assessment',
        };
    }
}
