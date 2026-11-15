<?php

namespace App\Enums;

/**
 * Built-in roles (REQ §27). Extra roles can be created at runtime from the
 * Roles & Permissions screen; these five are required by the business rules.
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case LeadManager = 'lead-manager';
    case Counselor = 'counselor';
    case Accounts = 'accounts';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::LeadManager => 'Lead Manager',
            self::Counselor => 'Counselor',
            self::Accounts => 'Accounts',
            self::Student => 'Student',
        };
    }

    /**
     * The dashboard area a user with this role lands in after login.
     */
    public function area(): Area
    {
        return match ($this) {
            self::SuperAdmin, self::LeadManager => Area::Admin,
            self::Counselor => Area::Counselor,
            self::Accounts => Area::Accounts,
            self::Student => Area::Portal,
        };
    }
}
