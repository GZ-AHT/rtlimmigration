<?php

namespace App\Models;

use App\Support\Cms\WebsiteMenus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A website menu at one location (REQ §21.3 Main / Footer / Top / Mobile menu).
 *
 * @property int $id
 * @property string $name
 * @property string $location
 */
#[Fillable(['name', 'location'])]
class Menu extends Model
{
    public const LOCATIONS = [
        'main' => 'Main navigation',
        'top' => 'Top bar',
        'mobile' => 'Mobile menu',
        'footer' => 'Footer: Explore',
        'footer_services' => 'Footer: Our services',
        'footer_legal' => 'Footer: Legal links',
    ];

    protected static function booted(): void
    {
        // The website caches its menus (App\Support\Cms\WebsiteMenus).
        static::saved(fn () => WebsiteMenus::forget());
        static::deleted(fn () => WebsiteMenus::forget());
    }

    /** @return HasMany<MenuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
