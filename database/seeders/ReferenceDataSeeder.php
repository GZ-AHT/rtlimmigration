<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\DocumentCategory;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Service;
use App\Models\TicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starting reference data. Only inserts rows that are missing, so changes
 * made by administrators are never overwritten. Destinations and services
 * are the ones shown in the Figma designs and admin-demo.html.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            'AU' => 'Australia', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'US' => 'United States',
            'NZ' => 'New Zealand', 'DK' => 'Denmark', 'AM' => 'Armenia',
        ];
        $sort = 0;

        foreach ($destinations as $iso2 => $name) {
            Country::firstOrCreate(['iso2' => $iso2], ['name' => $name, 'is_destination' => true, 'sort_order' => $sort += 10]);
        }

        Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'is_destination' => false]);

        // Figma: homepage service finder tabs and footer "Our services".
        foreach (['Student visa', 'Schooling visa', 'Visitor visa', 'Skilled migration'] as $i => $name) {
            Service::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'sort_order' => ($i + 1) * 10]);
        }

        // Document checklist (REQ §10 "Documents Management"). Required ones
        // make up each student's checklist; admins can change this.
        $documents = [
            ['Passport', true, 'Bio-data page of a valid passport.'],
            ['Passport-size photo', true, 'Recent photo with a white background.'],
            ['Academic certificates', true, 'SSC, HSC, degree certificates.'],
            ['Academic transcripts', true, 'Mark sheets for each qualification.'],
            ['English test result', false, 'IELTS, PTE, TOEFL or Duolingo score report.'],
            ['National ID / Birth certificate', false, null],
            ['Bank statement', false, 'Proof of funds.'],
            ['CV / Resume', false, null],
            ['Statement of purpose', false, null],
            ['Recommendation letter', false, null],
            ['Offer letter', false, 'Admission or job offer.'],
            ['Other', false, null],
        ];

        foreach ($documents as $i => [$name, $required, $description]) {
            DocumentCategory::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'is_required' => $required, 'description' => $description, 'sort_order' => ($i + 1) * 10,
            ]);
        }

        // REQ §12.2 payment types. No amounts are seeded: REQ says the
        // example fees are illustrative, so Admin sets real amounts.
        $types = ['File Opening Fee', 'Registration Fee', 'Application Processing Fee', 'Document Verification Fee',
            'University Application Fee', 'Admission Processing Fee', 'Visa Processing Fee', 'Medical Fee', 'Embassy Fee', 'Service Charge'];

        foreach ($types as $i => $name) {
            PaymentType::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'generate_on_file_open' => $name === 'File Opening Fee',
                'sort_order' => ($i + 1) * 10,
            ]);
        }

        // REQ §12.3 payment methods. Instructions (account numbers) are left
        // for Admin to fill in.
        $methods = [
            ['Bank transfer / deposit', 'bank_transfer', true, true, true],
            ['bKash / Nagad / Rocket', 'mobile_banking', true, false, true],
            ['Cash at office', 'cash', false, false, false],
            ['Debit / credit card (office POS)', 'card', true, false, false],
            ['Manual entry', 'manual', false, false, false],
        ];

        foreach ($methods as $i => [$name, $kind, $reference, $evidence, $studentSelectable]) {
            PaymentMethod::firstOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'kind' => $kind, 'requires_reference' => $reference, 'requires_evidence' => $evidence,
                'student_selectable' => $studentSelectable, 'sort_order' => ($i + 1) * 10,
            ]);
        }

        foreach (['General enquiry', 'Admission', 'Visa', 'Payment', 'Documents', 'Technical support'] as $i => $name) {
            TicketCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'sort_order' => ($i + 1) * 10]);
        }
    }
}
