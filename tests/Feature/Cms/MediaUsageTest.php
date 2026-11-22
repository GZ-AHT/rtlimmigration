<?php

use App\Enums\PublishStatus;
use App\Enums\Role;
use App\Models\BlogPost;
use App\Models\Country;
use App\Models\Media;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\University;
use App\Support\Cms\MediaUsage;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
    $this->admin = $this->userWithRole(Role::SuperAdmin);
});

function libraryFile(string $name = 'x.jpg'): Media
{
    return Media::create(['disk' => 'public', 'path' => "media/{$name}", 'original_name' => $name, 'mime_type' => 'image/jpeg', 'size' => 10]);
}

describe('finding where a file is used', function () {
    it('finds pages, posts, universities, testimonials and site settings', function () {
        [$page, $post, $uni, $quote, $logo, $spare] = array_map(fn ($n) => libraryFile("{$n}.jpg"), ['page', 'post', 'uni', 'quote', 'logo', 'spare']);

        Page::create(['title' => 'Home', 'slug' => 'home', 'status' => PublishStatus::Published, 'sections' => [
            ['id' => 'a1', 'type' => 'hero', 'enabled' => true, 'data' => ['image' => $page->id], 'settings' => []],
        ]]);
        BlogPost::create(['title' => 'Canada guide', 'slug' => 'canada-guide', 'content' => '<p>x</p>', 'status' => PublishStatus::Draft, 'featured_image_id' => $post->id]);
        University::create(['name' => 'Maple University', 'slug' => 'maple', 'country_id' => Country::where('iso2', 'CA')->value('id'), 'is_active' => true, 'logo_id' => $uni->id]);
        Testimonial::create(['name' => 'Rafi', 'quote' => 'Great', 'photo_id' => $quote->id, 'is_active' => true]);
        app(SettingsRepository::class)->set('branding.logo', $logo->id, public: true);

        $usage = MediaUsage::for([$page->id, $post->id, $uni->id, $quote->id, $logo->id, $spare->id]);

        expect($usage[$page->id])->toBe(['Page: Home'])
            ->and($usage[$post->id])->toBe(['Blog post: Canada guide'])
            ->and($usage[$uni->id])->toBe(['University: Maple University'])
            ->and($usage[$quote->id])->toBe(['Testimonial: Rafi'])
            ->and($usage[$logo->id][0])->toStartWith('Site setting:')
            ->and($usage)->not->toHaveKey($spare->id);
    });

    it('lists a place once even when a file appears there twice', function () {
        $file = libraryFile();
        University::create(['name' => 'Maple University', 'slug' => 'maple', 'country_id' => Country::where('iso2', 'CA')->value('id'), 'is_active' => true, 'logo_id' => $file->id, 'banner_id' => $file->id, 'gallery' => [$file->id]]);

        expect(MediaUsage::for([$file->id])[$file->id])->toBe(['University: Maple University'])
            ->and(MediaUsage::for([]))->toBe([]);
    });
});

describe('deleting from the library', function () {
    it('deletes a file nothing uses', function () {
        $file = libraryFile();

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $file))->assertSessionHasNoErrors();

        expect(Media::find($file->id))->toBeNull();
    });

    it('refuses a file that is in use unless the person confirms', function () {
        $file = libraryFile();
        BlogPost::create(['title' => 'Canada guide', 'slug' => 'canada-guide', 'content' => '<p>x</p>', 'status' => PublishStatus::Draft, 'featured_image_id' => $file->id]);

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $file))
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');
        expect(Media::find($file->id))->not->toBeNull();

        $this->delete(route('admin.media.destroy', $file), ['force' => true])->assertSessionHas('inertia.flash_data.toast.type', 'success');
        expect(Media::find($file->id))->toBeNull()
            ->and(BlogPost::sole()->featured_image_id)->toBeNull()
            ->and(Activity::query()->where('description', 'In-use media file deleted')->count())->toBe(1);
    });

    it('stays limited to people who manage media', function () {
        $file = libraryFile();

        $this->actingAs($this->userWithRole(Role::Counselor))->delete(route('admin.media.destroy', $file), ['force' => true])->assertForbidden();

        expect(Media::find($file->id))->not->toBeNull();
    });
});

describe('the library page', function () {
    it('shows where each file is used, but not to the picker inside editors', function () {
        $used = libraryFile('used.jpg');
        $free = libraryFile('free.jpg');
        BlogPost::create(['title' => 'Canada guide', 'slug' => 'canada-guide', 'content' => '<p>x</p>', 'status' => PublishStatus::Draft, 'featured_image_id' => $used->id]);

        $this->actingAs($this->admin)->get(route('admin.media.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('media.data.1.used_in', ['Blog post: Canada guide'])->where('media.data.0.used_in', []));

        $json = $this->getJson(route('admin.media.index'))->assertOk()->json('data.0');
        expect($json)->not->toHaveKey('used_in')->and($json['id'])->toBe($free->id);
    });
});
