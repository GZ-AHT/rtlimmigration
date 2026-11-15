<?php

namespace App\Enums;

/**
 * The four authenticated dashboard areas sharing one database (REQ §26, §30).
 */
enum Area: string
{
    case Admin = 'admin';
    case Counselor = 'counselor';
    case Accounts = 'accounts';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Super Admin',
            self::Counselor => 'Counselor',
            self::Accounts => 'Accounts',
            self::Portal => 'Student Portal',
        };
    }

    public function dashboardRoute(): string
    {
        return "{$this->value}.dashboard";
    }

    /**
     * Permission required to enter the area at all. Individual screens add
     * their own permission checks on top of this.
     */
    public function accessPermission(): string
    {
        return "area.{$this->value}";
    }
}
