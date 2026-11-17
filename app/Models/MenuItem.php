<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * REQ §21.3 menu link: internal page, custom/external URL, new tab,
 * parent/child, visibility (everyone, guests, signed in, chosen roles).
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $label
 * @property string $link_type
 * @property int|null $page_id
 * @property string|null $url
 * @property bool $new_tab
 * @property bool $is_active
 * @property string $visibility
 * @property list<string>|null $roles
 * @property int $sort_order
 * @property-read Page|null $page
 */
#[Fillable(['menu_id', 'parent_id', 'label', 'link_type', 'page_id', 'url', 'new_tab', 'is_active', 'visibility', 'roles', 'sort_order'])]
class MenuItem extends Model
{
    protected function casts(): array
    {
        return ['new_tab' => 'boolean', 'is_active' => 'boolean', 'roles' => 'array', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function href(): ?string
    {
        return match ($this->link_type) {
            'page' => $this->page ? ($this->page->system_key === 'home' ? '/' : '/'.$this->page->slug) : null,
            default => $this->url,
        };
    }
}
