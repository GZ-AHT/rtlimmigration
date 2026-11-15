<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Default website settings (REQ §21.1). Values come from the Figma designs
 * and are only inserted when missing, so admin edits are never overwritten.
 * Brand name per roadmap Q15: a CMS setting seeded with the Figma values.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'general.site_name' => 'RTL Bangladesh',
            'general.tagline' => 'Immigration & Visa Advisory',
            'general.description' => 'Personal guidance for your global journey.',
            'general.contact_email' => 'info@rtlbangladesh.com',
            'general.phone' => '+880 1806 428212',
            'general.whatsapp' => '+880 1806 428212',
            'general.address' => 'Gulshan 1, Dhaka, Bangladesh',
            'general.timezone' => 'Asia/Dhaka',
            'general.date_format' => 'd M Y',
            'general.currency' => 'BDT',
            'general.currency_symbol' => '৳',
            'general.default_language' => 'en',
        ];

        $settings = app(SettingsRepository::class);

        foreach ($defaults as $key => $value) {
            [$group, $name] = explode('.', $key, 2);

            if (! Setting::where('group', $group)->where('key', $name)->exists()) {
                $settings->set($key, $value, public: true);
            }
        }
    }
}
