<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEMO ONLY: sample student reviews so the reviews page and the homepage
 * "Student stories" slider can be seen with several entries. These are
 * invented and must never be published as real testimonials, so this
 * seeder refuses to run outside the local environment and is not part of
 * DatabaseSeeder.
 *
 *   php artisan db:seed --class=DemoReviewsSeeder
 *
 * Remove them in Admin → Website CMS → Testimonials before launch (every
 * one has the role text ending in "(demo)").
 */
class DemoReviewsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('DemoReviewsSeeder adds invented reviews and only runs in the local environment.');
        }

        $country = fn (string $iso2) => Country::query()->where('iso2', $iso2)->value('id');

        // [name, role, country, rating, featured, quote]
        $reviews = [
            ['Tanvir Ahmed', 'Bachelor’s student, University of Toronto', 'CA', 5, true, 'From choosing my course to the visa interview, RTL explained every step clearly. I never felt lost, and my visa was approved on the first try.'],
            ['Nusrat Jahan', 'Master’s student, Monash University', 'AU', 5, true, 'My adviser helped me compare universities honestly, not just push one option. The document checklist saved me weeks of stress.'],
            ['Rafiul Islam', 'Diploma student, Auckland', 'NZ', 5, false, 'I was worried my grades were not good enough. RTL found a diploma pathway that fits me, and now I am studying in New Zealand.'],
            ['Sadia Rahman', 'Master’s student, University of Leeds', 'GB', 4, true, 'Friendly team and quick replies on WhatsApp. They prepared me well for the credibility interview, which was the part I feared most.'],
            ['Mahmudul Hasan', 'Bachelor’s student, Copenhagen', 'DK', 5, false, 'Denmark was new to me and my family. RTL answered all our questions in a video call and made the whole process feel simple.'],
            ['Farhana Akter', 'Student visa, Yerevan', 'AM', 4, false, 'Good guidance on costs and accommodation in Armenia. The team followed up even after I arrived, which I really appreciated.'],
            ['Arif Chowdhury', 'Master’s student, Arizona State University', 'US', 5, false, 'The mock interview sessions were excellent. I walked into the embassy confident and came out with my F-1 visa.'],
            ['Ishrat Jahan', 'Parent of a Bachelor’s student', 'CA', 5, false, 'As a parent I wanted transparency about fees and timelines. RTL kept us informed at every stage. Thank you for looking after my daughter’s future.'],
        ];

        foreach ($reviews as $i => [$name, $role, $iso2, $rating, $featured, $quote]) {
            Testimonial::query()->updateOrCreate(['name' => $name], [
                'role' => $role.' (demo)',
                'country_id' => $country($iso2),
                'quote' => $quote,
                'rating' => $rating,
                'is_active' => true,
                'is_featured' => $featured,
                'sort_order' => 100 + $i * 10,
            ]);
        }
    }
}
