<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\PaymentType;
use App\Models\Service;
use App\Models\TicketCategory;
use App\Models\VisaType;
use App\Models\Workflow;
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
            // Filed by the gateway callback only; never entered by hand.
            ['Online payment (SSLCommerz)', 'gateway', false, false, false],
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

        $this->seedVisaTypesAndWorkflows();

        // Roadmap Q12: starting categories for expense records; Accounts can add more.
        $expenseCategories = ['Office rent', 'Salaries', 'Utilities', 'Marketing', 'Embassy & visa fees', 'University fees',
            'Courier & postage', 'Travel', 'Bank & gateway charges', 'Other'];

        foreach ($expenseCategories as $i => $name) {
            ExpenseCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'sort_order' => ($i + 1) * 10]);
        }
    }

    /**
     * REQ §13 suggested stages, plus roadmap Q10 and Q14: one engine, with
     * Working Visa and Work & Examination as visa types that have their own
     * checklist and workflow. Only seeded once; after that Super Admin owns
     * them (Visa types, Workflows & stages).
     */
    private function seedVisaTypesAndWorkflows(): void
    {
        $types = [
            ['Study visa', 'study', 'University and college admission with a student visa.', ['passport', 'passport-size-photo', 'academic-certificates', 'academic-transcripts', 'english-test-result', 'bank-statement', 'statement-of-purpose', 'offer-letter']],
            ['Working visa', 'work', 'Employer-sponsored work permit and visa.', ['passport', 'passport-size-photo', 'cv-resume', 'offer-letter', 'national-id-birth-certificate']],
            ['Visitor visa', 'visitor', 'Tourist, family and business visits.', ['passport', 'passport-size-photo', 'bank-statement']],
            ['Work & examination', 'exam', 'Language and skills examinations and work assessments needed for an application.', ['passport', 'passport-size-photo', 'national-id-birth-certificate']],
        ];

        foreach ($types as $i => [$name, $kind, $description, $documents]) {
            $type = VisaType::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'kind' => $kind, 'description' => $description, 'sort_order' => ($i + 1) * 10]);

            if ($type->wasRecentlyCreated) {
                $type->documentCategories()->sync(DocumentCategory::whereIn('slug', $documents)->pluck('id'));
            }
        }

        if (Workflow::exists()) {
            return;
        }

        $decision = 'Visa Approved / Visa Rejected';
        $closing = [
            [$decision, 'decision', 'The embassy has decided. Your counselor will explain the outcome.'],
            ['Office Visit Requested', 'visit_requested', 'Please visit our office. Your counselor will confirm the time.'],
            ['Office Visit Completed', 'visit_completed', null],
            ['Process Completed', 'final', null],
        ];

        $workflows = [
            ['Standard visa process', null, [
                ['File Opened', 'standard', 'Your file is open and your counselor is preparing your case.'],
                ['Initial Documents Pending', 'standard', 'Please upload the documents on your checklist.'],
                ['Documents Submitted', 'standard', null],
                ['Document Verification', 'standard', 'We are checking your documents.'],
                ['University / Employer Application', 'standard', null],
                ['Application Submitted', 'standard', null],
                ['Application Under Review', 'standard', null],
                ['Additional Documents Required', 'standard', 'More documents are needed. Your counselor will tell you which.'],
                ['Interview Scheduled', 'standard', null],
                ['Visa Application Submitted', 'standard', null],
                ['Visa Processing', 'standard', 'The embassy is processing your visa.'],
                ['Decision Received', 'standard', null],
                ...$closing,
            ]],
            ['Working visa process', 'working-visa', [
                ['File Opened', 'standard', 'Your file is open and your counselor is preparing your case.'],
                ['Documents Submitted', 'standard', null],
                ['Document Verification', 'standard', 'We are checking your documents.'],
                ['Job Offer / Employer Application', 'standard', null],
                ['Work Permit Application Submitted', 'standard', null],
                ['Work Permit Under Review', 'standard', null],
                ['Additional Documents Required', 'standard', 'More documents are needed. Your counselor will tell you which.'],
                ['Interview / Biometrics Scheduled', 'standard', null],
                ['Visa Application Submitted', 'standard', null],
                ['Visa Processing', 'standard', 'The embassy is processing your visa.'],
                ...$closing,
            ]],
            ['Work & examination process', 'work-examination', [
                ['Registered', 'standard', 'You are registered. Your counselor will book the examination.'],
                ['Documents Submitted', 'standard', null],
                ['Examination Booked', 'standard', 'Your examination is booked. Check the date on your application.'],
                ['Examination Taken', 'standard', null],
                ['Result Received', 'standard', 'Your result is in. See it on your application.'],
                ['Process Completed', 'final', null],
            ]],
        ];

        foreach ($workflows as [$name, $typeSlug, $stages]) {
            $workflow = Workflow::create(['name' => $name, 'visa_type_id' => $typeSlug ? VisaType::where('slug', $typeSlug)->value('id') : null]);

            foreach ($stages as $i => [$stage, $kind, $description]) {
                $workflow->stages()->create(['name' => $stage, 'kind' => $kind, 'description' => $description, 'sort_order' => ($i + 1) * 10]);
            }
        }
    }
}
