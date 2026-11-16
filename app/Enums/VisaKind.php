<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * The family a visa type belongs to (roadmap Q14: one engine, many types).
 * It only decides which menu lists the type and how a few fields are
 * labelled; workflows, documents and fees are set per type.
 */
enum VisaKind: string
{
    use HasOptions;

    case Study = 'study';
    case Work = 'work';
    case Visitor = 'visitor';
    case Exam = 'exam';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Study => 'Study visa',
            self::Work => 'Working visa',
            self::Visitor => 'Visitor visa',
            self::Exam => 'Work & examination',
            self::Other => 'Other',
        };
    }

    /** What the "title" and "organisation" fields mean for this family. */
    public function titleLabel(): string
    {
        return match ($this) {
            self::Study => 'Programme / course',
            self::Work => 'Job title',
            self::Exam => 'Examination',
            default => 'Purpose',
        };
    }

    public function organisationLabel(): string
    {
        return match ($this) {
            self::Study => 'University / institution',
            self::Work => 'Employer',
            self::Exam => 'Test centre / board',
            default => 'Sponsor / host',
        };
    }

    public function scheduleLabel(): string
    {
        return $this === self::Exam ? 'Examination date' : 'Interview date';
    }
}
