<?php

use App\Models\Setting;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->settings = app(SettingsRepository::class));

it('stores and reads typed values', function () {
    $this->settings->set('general.site_name', 'RTL Bangladesh');
    $this->settings->set('header.sticky', true);
    $this->settings->set('footer.columns', ['Explore', 'Services']);

    expect($this->settings->get('general.site_name'))->toBe('RTL Bangladesh')
        ->and($this->settings->get('header.sticky'))->toBeTrue()
        ->and($this->settings->get('footer.columns'))->toBe(['Explore', 'Services'])
        ->and($this->settings->get('missing.key', 'fallback'))->toBe('fallback');
});

it('returns a whole group without the prefix', function () {
    $this->settings->set('general.site_name', 'RTL');
    $this->settings->set('general.phone', '+880');
    $this->settings->set('seo.title', 'Ignored');

    expect($this->settings->group('general'))->toBe(['site_name' => 'RTL', 'phone' => '+880']);
});

it('encrypts secret values at rest and never makes them public', function () {
    $this->settings->set('mail.smtp_password', 's3cret', encrypted: true, public: true);

    $raw = Setting::where('key', 'smtp_password')->first();

    expect($raw->value)->not->toContain('s3cret')
        ->and($raw->is_public)->toBeFalse()
        ->and($this->settings->get('mail.smtp_password'))->toBe('s3cret')
        ->and($this->settings->public())->not->toHaveKey('mail');
});

it('only exposes settings flagged public', function () {
    $this->settings->set('general.site_name', 'RTL', public: true);
    $this->settings->set('general.internal_note', 'private');

    expect($this->settings->public())->toBe(['general' => ['site_name' => 'RTL']]);
});

it('sees writes immediately despite caching', function () {
    $this->settings->set('general.site_name', 'Old');
    expect($this->settings->get('general.site_name'))->toBe('Old');

    $this->settings->set('general.site_name', 'New');
    expect(app(SettingsRepository::class)->get('general.site_name'))->toBe('New');
});

it('rejects keys without a group', function () {
    $this->settings->set('nogroup', 'x');
})->throws(InvalidArgumentException::class);
