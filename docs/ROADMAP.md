# RTL Immigration IMS + CMS — Development Roadmap

**Status:** Approved 2026-11-15. **Phases 0–6 complete**; Phase 7 (Tawk.to live chat) next. Answered: **Q3** (SSLCommerz first, with Easy Checkout), **Q10** (Work & Examination is a module of the visa engine), **Q12** (yes, add expense records) and **Q14** (one application engine for all visa types), plus: students may request refunds. The other questions are still open. Q11 (login page styling) affects work already done.

**Phase 0 notes**

- Passkeys (from the starter kit) are kept enabled as an optional sign-in method. REQ does not require them. Remove them or keep them? Owner to decide.
- Default refund approval sits with Super Admin only (`refunds.approve` is not given to Accounts). REQ §27 rule 10 says "authorized approval" without naming the role. Owner to confirm.
- Website colours were sampled from the Figma frames (the file defines no variables). They will be re-verified section by section in Phase 9.

**Phase 1 notes**

- Login, registration and password screens keep the starter-kit styling until Q11 is answered.
- Deleted users are soft-deleted and can be restored. This includes self-deletion from profile settings (a starter-kit feature REQ doesn't mention), so payment and file records stay intact. A deleted user's email stays reserved. Owner to decide whether students may delete their own accounts at all.
- New self-registrations notify active Super Admins in-app (REQ §2, §20).
- Real-time notifications (Reverb) are not wired yet. The bell updates on each page load. That's scheduled for Phase 12, pending Q5 (hosting).

**Phase 2 notes**

- "All Contact Inquiries" (REQ §7) is the list of leads that came in through the website form, not a separate store, because REQ §11 says website enquiries become leads.
- Destinations (7) and services (4) are seeded from the Figma designs and the admin demo. Admins can edit them once country and service management lands in Phase 9.
- The public `/contact` page has only the Figma "Consultation enquiry" section. Its copy is hardcoded until the full Figma page and CMS arrive in Phase 9.
- Follow-up reminders need the Laravel scheduler (`* * * * * php artisan schedule:run`) running on the server.
- Lead visibility: anyone with `leads.assign` sees all leads; everyone else sees only leads assigned to them. Student visibility works the same way, keyed on `students.assign-counselor`.

**Phase 3 notes**

- A student can have several files (one per application). Each file has a unique number (`FILE-YYYY-#####`) and an administrative status (open / on hold / completed / closed). Visa stages are separate (Phase 6).
- Opening a file fires `StudentFileOpened`. Phase 4 listens for it to raise the file-opening fee payment request (REQ §11 step 6).
- Passport numbers are encrypted at rest, masked on staff screens, and never written to the audit log.
- Documents sit on the private `local` disk under random names and are served only through an authorised route, with `nosniff` and a sandboxing CSP. Uploads are checked by file content, not just extension. Allowed types: PDF, JPG, PNG, WEBP, DOC and DOCX. The 10 MB limit is configurable in `config/documents.php` and moves to System Settings in Phase 11.
- Reviewed documents are permanent, including for Super Admin. Only pending documents can be withdrawn, and replacing one keeps the previous version.
- Document categories (and which are required) are managed by users with `settings.manage` (Super Admin by default).
- Students can edit their own personal and passport details in the portal (REQ §10.1), and every change is audit-logged. Locking details after verification is not in REQ; owner to decide whether it's wanted.

**Phase 4 notes**

- All money goes through `App\Actions\Payments\PaymentLedger`. An invoice's status is derived from its payments, never typed in. Amounts are stored as integer poisha.
- No fee amounts are seeded (REQ says its example amounts are illustrative). The File Opening Fee is raised automatically on file opening only once an admin sets its default amount in Payment Types; until then the counselor is notified to raise it by hand. Owner to set the real amounts.
- Payment methods are seeded without account details. Accounts or an admin must add the bank account and mobile-banking numbers as instructions before students can pay sensibly.
- Whoever raised an invoice can never confirm or reject its payments, Super Admin included (REQ §27 rule 6, applied to everyone as separation of duties).
- Accounts can "verify" and then "confirm" as two steps, or confirm directly. Owner to say if two different people must be required.
- Accounts were given `payment-types.manage` because REQ §9.1 lists "Payment Methods" among the Accounts menus. That permission also covers payment types in the admin panel; split it if Accounts shouldn't edit fee types.
- The same transaction reference can't be submitted twice for the same method unless the earlier payment was rejected.
- Overdue marking needs the scheduler (`payments:mark-overdue`, daily).
- Not in this phase: online gateway, refunds, PDF invoices and receipts, due and partial payment lists, bank and gateway transaction views, expenses and financial reports (all Phase 5). Visa figures on the dashboards arrive with Phase 6.

**Phase 5 notes**

- Refunds go: requested (against one confirmed payment) → approved → paid out by Accounts. Whoever requested a refund can't approve it. By default **only Super Admin can approve**; give `refunds.approve` to another role in Roles & Permissions if someone else should.
- Students can ask for a refund from their payment history (confirmed by the owner 2026-11-16). Nothing is paid until it is approved.
- Refunds are paid back by hand (bank, bKash, cash) and then recorded. The system does not call SSLCommerz's refund API.
- "Refund requested" is shown on an invoice only when it is otherwise fully paid. A part-paid invoice stays on the due lists while a refund is in progress, because the balance is still owed.
- Online payment (SSLCommerz) is **off until credentials are set**: `PAYMENT_GATEWAY=sslcommerz`, `SSLCOMMERZ_STORE_ID`, `SSLCOMMERZ_STORE_PASSWORD`, and `SSLCOMMERZ_SANDBOX=false` for live. Only the hosted checkout is built; aamarPay, bKash and Nagad direct can be added as drivers.
- A payment is recorded only after our server validates it with SSLCommerz (transaction ID, amount and currency must match). The browser returning to the success page proves nothing. Repeat callbacks are recorded once.
- A payment SSLCommerz flags as risky is not confirmed automatically. It waits in Pending Verification for Accounts.
- If a student pays online more than is owed (for example in two tabs), the money is still recorded, with a note to refund the excess.
- SSLCommerz's server-to-server notice (IPN) needs a public URL. On a local machine only the browser return settles the payment, so a student who closes the tab early stays unconfirmed until the IPN can reach the site.
- Invoice and receipt PDFs print "BDT" instead of ৳, because the PDF font has no Taka sign.
- Income is counted on the day Accounts confirmed a payment. Counselor-wise collection uses the counselor a student has today, not the one at the time of payment.
- Day boundaries in reports follow the server timezone (UTC), not Asia/Dhaka, like the rest of the app. That moves to System Settings in Phase 11.
- Removing an expense hides it from reports but keeps the record. Every report export is written to the activity log.
- Fixed from Phase 4: Accounts could not use "Record money received" with a bank transfer, because the proof-upload rule still applied.
- Not built: yearly comparison charts and the non-finance reports of REQ §24 (Phase 12); automatic refunds through the gateway.

**Phase 6 notes**

- One application engine serves every visa type (Q14). A visa type has a family (study, working, visitor, work & examination, other), its own required-document checklist, requirements and fee. Super Admin adds types under Visa Processing → Visa types; no code is needed for a new category.
- Workflows are lists of stages, edited under Visa Processing → Workflows & stages. A workflow for a visa type and country beats one for the visa type, which beats the default. Seeded: the 16 stages of REQ §13 (default), a working visa process and a work & examination process.
- A stage is a plain progress step unless it is marked as decision, office visit requested, office visit completed or process completed. Staff may move an application to any stage, forwards or back, because real cases loop (for example "Additional Documents Required").
- A decision needs approved or rejected, the decision document, and a reason if rejected. It needs the `applications.decide` permission, which counselors have by default. The student is always notified of a decision, and sees the rejection reason.
- An application cannot be marked "Process Completed" before its decision is recorded (when its workflow has a decision stage). Completed and withdrawn applications are locked.
- Work & Examination (Q10) is a visa type with its own workflow, not a separate system. An examination is an application with an examination name, test centre, date and result. It has no approve/reject decision. The REQ §14 items "Exam Categories" and "Exam Reports" are covered by visa types of that family and the filtered list; there is no separate exam catalogue or timetable. Say if you need one.
- Working Visa is the same screens filtered to working-visa types. Admin sees it with `working-visa.view`.
- Documents attached to a status update are filed as verified (staff issued or checked them) and appear on the student's timeline.
- "Raise fee" on an application creates an ordinary invoice for the visa type's fee. Payments and visa progress stay separate: an unpaid fee does not block a stage change. Owner to say if it should.
- Students see their applications, progress, checklist and timeline, but not which member of staff made an update, and never the internal notes.
- Not built: "Visa SEO" (Phase 10), country management screens (Phase 9), visa report exports (Phase 12).

**Easy Checkout (added 2026-11-16)**

- "Pay online" now opens SSLCommerz Easy Checkout, their popup over the payments page. Set `SSLCOMMERZ_CHECKOUT=hosted` to go back to redirecting to SSLCommerz's own page.
- Phones still use the redirect: SSLCommerz's popup script opens a new tab on mobile after a delay, which mobile browsers often block. If their script fails to load, desktop falls back to the redirect too.
- The popup is only a different way to reach SSLCommerz. Payments are still recorded only after server-side validation. If the page comes back without SSLCommerz's usual fields, the server looks the transaction up with SSLCommerz by its ID.
- Checked in a browser with SSLCommerz's real script up to the point of opening a session. The payment itself has not been run end to end: that needs sandbox store credentials.
- Their script adds its own styles to the payments page, including an always-visible page scrollbar.

**Source of truth:** `RTL_Immigration_Complete_Admin_Dashboard_Requirements.md` (referred to below as **REQ**, with § section numbers). The user confirmed on 2026-11-15 that this file stands in for `PROJECT_REQUIREMENTS.md`.
**Design references:**

- Public website: Figma `RTLIMMIG` (file key `7XLQdh0vVNJdu3jjMQ9Dat`, page `0:1`).
- Admin panel: `admin-demo.html` in the project root.

---

## 1. Current state (what was analysed)

| Item              | Finding                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel project   | **None exists.** The repo holds only `README.md`, the requirements doc and `admin-demo.html`. The user confirmed a fresh install in this repo.                                                                                                                                                                                                                                                                                                                              |
| `admin-demo.html` | A static single-file demo (58 lines, about 38 KB). DM Sans and Material Symbols Outlined. Tokens: `--red #e80000`, `--ink #20232b`, `--muted #727784`, `--line #eceef2`, `--bg #f7f8fa`, green `#16865b`, amber `#b7791f`, blue `#3569c8`. Grouped sidebar (WORKSPACE / MANAGEMENT / SERVICES) with 14 modules and sub-pages, a breadcrumb top bar, KPI cards, data tables and a modal record form. "Petrep application" in the demo is a typo for **Partner application**. |
| Figma             | 6 desktop frames at 1440 px: **Homepage** `12:4`, **About us** `12:246`, **Partner application** `12:386`, **Contact** `12:539`, **FAQ** `12:675`, **Blog listing** `12:785`. No tablet or mobile frames, no variables or styles, and no component library. Visual language: white and warm-beige sections, RTL red accent, a dark navy band, large bold headings, red uppercase eyebrows, rounded cards and ↗ arrow CTAs.                                                  |

---

## 2. Technical decisions (proposed)

| Area             | Decision                                                                                                                                                                                                                                  | Reason / REQ link                                                         |
| ---------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Backend          | **Laravel 13**, PHP 8.3+                                                                                                                                                                                                                  | User instruction; REQ §31.3                                               |
| Frontend         | **Vue 3 + Inertia.js 2 + TypeScript** (`<script setup lang="ts">`)                                                                                                                                                                        | User instruction; REQ §31.3 recommends TS                                 |
| Starter          | **Official Laravel Vue starter kit** (Inertia, Vue, TS, Tailwind v4, Fortify auth)                                                                                                                                                        | Avoids hand-rolling auth; it already includes 2FA via Fortify (REQ §23.2) |
| Styling          | **Tailwind CSS v4** using CSS-first `@theme` tokens. There are **two token sets**: `website` (from Figma) and `admin` (from `admin-demo.html`).                                                                                           | REQ §31.2 says the two looks need not be identical                        |
| UI primitives    | Starter kit's shadcn-vue / Reka UI for accessible primitives (dialogs, dropdowns, popovers), restyled to the admin tokens                                                                                                                 | Accessibility (REQ §31.4) without a second framework                      |
| Typed routes     | Laravel Wayfinder (bundled with the starter kit)                                                                                                                                                                                          | Type-safe route calls from TS                                             |
| Database         | **MySQL 8** (or MariaDB 10.11+). SQLite only for tests.                                                                                                                                                                                   | No existing DB to inherit (REQ §31.3)                                     |
| RBAC             | `spatie/laravel-permission` with roles **Super Admin, Lead Manager, Counselor, Accounts, Student**, plus optional extras (Content Editor, Support)                                                                                        | REQ §6, §27                                                               |
| Authorization    | Laravel **Policies** on every model and action. The UI hides menus, but the server always checks.                                                                                                                                         | REQ §26, §31.5                                                            |
| Audit / activity | `spatie/laravel-activitylog` for general actions, plus **dedicated history tables** for payment status, application status and assignments                                                                                                | REQ §12.5 (rule 7), §13.3, §26                                            |
| Media            | `spatie/laravel-medialibrary` for the central library, conversions, alt text and private disks for student documents                                                                                                                      | REQ §23.4; documents must not be public                                   |
| Rich text        | **TipTap** (Vue 3)                                                                                                                                                                                                                        | REQ §4, §5                                                                |
| Page builder     | **Block/section-based builder**: sections stored as JSON with a typed schema, rendered by matching Vue section components, with drag-and-drop ordering                                                                                    | REQ §5, §21.7–21.8. Needs confirmation (Q7).                              |
| Drag & drop      | `vue-draggable-plus` (SortableJS)                                                                                                                                                                                                         | Menus and section ordering (§21.3, §21.7)                                 |
| Charts           | ApexCharts (`vue3-apexcharts`)                                                                                                                                                                                                            | Dashboards and reports                                                    |
| Exports          | `openspout` or `maatwebsite/excel` for Excel/CSV; `barryvdh/laravel-dompdf` for PDF invoices, receipts and reports                                                                                                                        | REQ §9, §24                                                               |
| Queues           | Database queue driver to start; Redis + Horizon if the hosting allows it                                                                                                                                                                  | Email, exports, webhooks                                                  |
| Real-time        | **Laravel Reverb** + Echo for in-app notifications and dashboard sync. Falls back to polling if hosting can't run a long-lived process.                                                                                                   | REQ §20, §27 (rule 7)                                                     |
| Search           | Laravel Scout with the database driver for global admin search                                                                                                                                                                            | REQ §1                                                                    |
| SEO              | `spatie/laravel-sitemap` and a custom SEO meta model (polymorphic `seo_meta`). Server-side meta via Inertia SSR.                                                                                                                          | REQ §22. SSR is needed so public pages are crawlable.                     |
| Backups          | `spatie/laravel-backup`                                                                                                                                                                                                                   | REQ §23.5                                                                 |
| Payments         | A **gateway abstraction** (`PaymentGateway` interface) with drivers. The first driver depends on the merchant account (Q3). Server-side IPN/validation, plus idempotency keys and a `webhook_events` table for dedup.                     | REQ §12.3, §12.5                                                          |
| Live chat        | Tawk.to JS widget with **Secure Mode** (HMAC of the visitor email, computed server-side), custom attributes (student no. / file no.), and webhooks into `chat_webhook_logs` with signature verification                                   | REQ §18                                                                   |
| Webmail          | **Roundcube as a separate install** on a subdomain (e.g. `mail.<domain>`), opened from the panel. SSO through a custom Roundcube plugin that validates a short-lived signed token issued by Laravel. Credentials never reach the browser. | REQ §19.3                                                                 |
| Testing          | **Pest** (feature tests for every policy and workflow), Vitest for complex components, Larastan level 6+, ESLint + Prettier, `vue-tsc`                                                                                                    | REQ §31.5                                                                 |
| i18n             | Build with translation keys from day one (`laravel-vue-i18n`). Languages depend on Q4.                                                                                                                                                    | REQ §21.1, §22.3 (hreflang)                                               |
| Money            | Store amounts as **integer minor units** (poisha) with a currency column. Default BDT ৳.                                                                                                                                                  | Avoids float errors                                                       |
| Numbering        | Generated, unique, sequence-safe numbers: student `STU-2026-00001`, file `FILE-…`, invoice `INV-…`, receipt `RCP-…`. Formats configurable.                                                                                                | REQ §11.2, §12.5                                                          |

### Architecture shape

```
app/
  Domain/                 # business logic, framework-light
    Leads/ Students/ Files/ Payments/ Finance/ Applications/ Cms/ Seo/ ...
      Actions/            # e.g. ConfirmPayment, AssignLead, AdvanceApplicationStage
      Enums/              # PaymentStatus, LeadStatus, ...
      Events/ Listeners/  # PaymentConfirmed -> notify + sync dashboards
  Http/Controllers/{Admin,Counselor,Accounts,Portal,Website}/
  Policies/
resources/js/
  layouts/  WebsiteLayout, AdminLayout, CounselorLayout, AccountsLayout, PortalLayout
  components/ui/      # DataTable, FilterBar, StatusBadge, Modal, ConfirmDialog, FileUpload, EmptyState, StatCard ...
  components/website/ # Figma sections: Hero, PathwayCards, StatsBand, FaqAccordion ...
  pages/{Admin,Counselor,Accounts,Portal,Website}/...
  types/              # shared domain TS types (generated from PHP DTOs where practical)
```

- **One database, one app, four authenticated areas.** `/admin` is used by Super Admin, Lead Manager and other staff. `/counselor`, `/accounts` and `/portal` (students) are the other three. The sidebar is built server-side from a menu registry filtered by permission (REQ §25) and shared to Inertia.
- **Payments and visa processing are separate state machines** linked only by application rules (REQ §30, "Important Workflow Separation"). Every status transition goes through one Action that validates the move, writes history and dispatches an event, all inside a DB transaction.

---

## 3. Core data model (initial)

Based on REQ §26, with additions marked ➕.

| Area         | Tables                                                                                                                                                                                                                                        |
| ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Identity     | `users`, `roles`, `permissions`, `model_has_roles`, `login_histories`, `user_invitations` ➕                                                                                                                                                  |
| Leads        | `leads`, `lead_assignments`, `lead_followups`, `lead_notes` ➕, `lead_contacts` ➕ (contact history)                                                                                                                                          |
| Students     | `students` (central record + student no.), `student_profiles` (personal / passport / education / contact), `student_files` (file no., status, counselor), `counselor_students`                                                                |
| Counselors   | `counselors` (profile, status, targets)                                                                                                                                                                                                       |
| Documents    | `documents` (polymorphic, private disk, verification status), `document_categories`                                                                                                                                                           |
| Payments     | `payment_types` (dynamic, §12.2), `payment_methods`, `payment_requests` (one per step, linked to file/application), `payments` (attempts/submissions incl. manual evidence), `payment_status_histories` ➕, `webhook_events` ➕ (idempotency) |
| Finance      | `invoices`, `invoice_items` ➕, `receipts` ➕, `transactions`, `refunds` (FK to original payment), `expenses` ➕ (see Q12)                                                                                                                    |
| Applications | `applications` (type: study / work / visitor / …; country; university; visa category), `workflows` + `workflow_stages` ➕ (configurable per country/visa type, §13), `application_status_histories`, `office_visits` ➕                       |
| Catalogue    | `countries`, `universities`, `courses`, `intakes`, `scholarships`, `visa_categories`, `visa_requirements`                                                                                                                                     |
| Partners     | `partner_applications`, `partners`, `partner_commissions`, `partner_payments`                                                                                                                                                                 |
| Exams/Work   | `exams`, `exam_categories`, `exam_assignments`, `exam_results` (scope depends on Q10)                                                                                                                                                         |
| Support      | `tickets`, `ticket_categories`, `ticket_replies`, `contact_inquiries`                                                                                                                                                                         |
| Comms        | `notifications` (Laravel), `notification_templates`, `activity_log`, `email_accounts`, `email_templates`, `email_logs`, `chat_integrations`, `chat_webhook_logs`                                                                              |
| CMS          | `settings` (typed key/value, grouped), `menus`, `menu_items`, `pages`, `page_revisions`, `page_sections`, `posts`, `post_categories`, `tags`, `comments`, `testimonials`, `faqs`, `faq_categories`, `newsletter_subscribers`                  |
| SEO          | `seo_meta` (polymorphic), `redirects`, `broken_links`                                                                                                                                                                                         |
| Media        | `media`, `media_folders`                                                                                                                                                                                                                      |

---

## 4. Requirements ↔ Figma ↔ admin demo comparison

### 4.1 Public website: pages REQ needs vs. what Figma provides

| Page (REQ §21.8 + nav)                      | In Figma?   | Plan                                                                          |
| ------------------------------------------- | ----------- | ----------------------------------------------------------------------------- |
| Home                                        | ✅ `12:4`   | Build exactly; every section CMS-driven                                       |
| About us                                    | ✅ `12:246` | Build exactly                                                                 |
| Contact us                                  | ✅ `12:539` | Build; the form creates a **Lead** (§11 step 1)                               |
| FAQ                                         | ✅ `12:675` | Build; FAQ categories and search                                              |
| Blog listing                                | ✅ `12:785` | Build; categories, search, pagination, newsletter                             |
| Partner application                         | ✅ `12:386` | Build; creates a `partner_application` (§16)                                  |
| Blog article detail                         | ❌          | Derive from Blog/About patterns (assumption flagged)                          |
| Our Services / Visa services (nav dropdown) | ❌          | Derive; service detail template                                               |
| Study Abroad                                | ❌          | Derive from Homepage pathway sections                                         |
| Working Visa                                | ❌          | Derive                                                                        |
| University listing + detail                 | ❌          | Derive from Blog card grid                                                    |
| All partners (nav)                          | ❌          | Derive                                                                        |
| Reviews / Student testimonials (nav)        | ❌          | Derive from the Homepage "Student stories" block                              |
| Privacy, Terms, Cookie policy               | ❌          | Simple CMS legal template                                                     |
| Custom landing pages                        | ❌          | Built from the section builder using Figma sections                           |
| Login / Register / Forgot password / 2FA    | ❌          | Derive from the website style (see Q11)                                       |
| 404, maintenance page                       | ❌          | Derive                                                                        |
| **Tablet / mobile layouts (all pages)**     | ❌          | Responsive adaptation of the desktop frames; screenshots sent for your review |
| Dark mode for the website                   | ❌          | See Q9                                                                        |

### 4.2 Homepage sections: REQ §21.7 vs. Figma

| REQ section                                                                                         | In Figma homepage                              | Note                                                                                                                  |
| --------------------------------------------------------------------------------------------------- | ---------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Hero, CTA buttons, title/description                                                                | ✅                                             | Figma shows a single image, not a **slider**. Slider support is added as a CMS option that uses the same hero design. |
| Service finder (visa-type tabs + destination + study level)                                         | ✅ (Figma only)                                | Not in REQ. It needs Countries and Courses data. See Q8.                                                              |
| About section, Why choose us, Statistics                                                            | ✅                                             |                                                                                                                       |
| Services section                                                                                    | ✅ (pathways: Diploma / Bachelor's / Master's) |                                                                                                                       |
| Testimonials                                                                                        | ✅                                             |                                                                                                                       |
| Blog section, FAQ section                                                                           | ✅                                             |                                                                                                                       |
| Video counseling block                                                                              | ✅ (Figma only)                                | **No video/booking module in REQ.** See Q6.                                                                           |
| University section, Popular countries, Featured courses, Partner logos, Newsletter, Contact section | ❌                                             | REQ wants them. They'll be built in the Figma style and can be switched off in the CMS.                               |

### 4.3 Admin demo vs. REQ §25 sidebar

The demo covers 14 modules; REQ §25 lists 26. Missing from the demo: Lead management, Student File management, Invoice & Refund, Visa Processing (as distinct from Working Visa), SEO, Website CMS, Reports, Notifications, Media Library, Integrations, Roles & Permissions, and Security & Audit logs. **Plan:** keep the demo's visual system (sidebar grouping style, cards, tables, forms, red/ink palette, DM Sans, Material Symbols) and lay out the **REQ §25 menu structure** inside it. The demo has no dark mode, so admin dark tokens will be derived from the light palette (REQ §21.6).

---

## 5. Conflicts, ambiguities and dependencies (need your decisions)

Each question has my **recommendation in bold**. I won't act on any of them until you confirm.

| #   | Topic                                                                                                  | Issue                                                                                                                                                                                                                                         | Recommendation                                                                                                                                                                                                                                                                                                                                                        |
| --- | ------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Q1  | **Email: §3 vs §19**                                                                                   | §3 lists a full in-app mail client (Inbox, Sent, Spam…). §19 says to use **Roundcube** as the webmail interface. Building both would duplicate work.                                                                                          | **Roundcube handles mailboxes** (Inbox, Sent, Drafts, Spam, Trash, Archive, Search, Address book). **Laravel handles** SMTP config, templates, signatures, bulk email, transactional notifications, delivery logs, student/counselor email history, account assignment and permissions. "Email Tracking (if supported)" means opens/clicks on Laravel-sent mail only. |
| Q2  | Mail server                                                                                            | Roundcube needs an IMAP/SMTP server and mailbox accounts. Who provides these (cPanel hosting, Google Workspace, a self-hosted server)? "Create Email Account" from the admin panel only works if the mail host has an API (e.g. cPanel UAPI). | **Tell me the mail host.** Until then, account creation in the admin panel records assignments only.                                                                                                                                                                                                                                                                  |
| Q3  | Payment gateway ✅ answered 2026-11-16: SSLCommerz first                                               | §12.3 names SSLCommerz, aamarPay, bKash and Nagad. Do you have merchant accounts? Which comes first?                                                                                                                                          | **SSLCommerz first** (it covers cards, bKash, Nagad and bank in one), plus manual bank/cash entry with evidence upload. Other gateways added as drivers later.                                                                                                                                                                                                        |
| Q4  | Languages                                                                                              | §21.1 / §22.3 mention website language, a default language and hreflang. Is Bangla content required at launch?                                                                                                                                | **English at launch, i18n-ready.** Add Bangla when content exists.                                                                                                                                                                                                                                                                                                    |
| Q5  | Hosting                                                                                                | Reverb (real-time), queue workers, the Roundcube install, scheduled backups and Inertia SSR all need a **VPS** (or similar). Shared hosting limits these.                                                                                     | **VPS with Nginx, PHP-FPM, MySQL, Redis and Supervisor.** What's the target environment?                                                                                                                                                                                                                                                                              |
| Q6  | Video counseling (Figma)                                                                               | Figma shows a video room UI ("RTL video room", mic/camera/end-call). REQ has **no** video or appointment-booking module.                                                                                                                      | **Treat it as a marketing section** whose CTA opens the consultation enquiry form, with "video" as the contact preference. A real booking or video system would be a new module and needs your approval.                                                                                                                                                              |
| Q7  | Page builder                                                                                           | "Page Builder / Rich Text Editor" could mean a visual drag-and-drop builder or a rich text editor.                                                                                                                                            | **Section-based builder** (pick from Figma-styled sections, edit fields, reorder, show/hide) **plus TipTap** for rich text blocks. No free-form pixel builder.                                                                                                                                                                                                        |
| Q8  | Service finder (Figma)                                                                                 | The homepage visa tabs + destination + study level → "Find my pathway". What should the result be?                                                                                                                                            | **A filtered University/Course listing page.** Needs the University module (Phase 9 dependency).                                                                                                                                                                                                                                                                      |
| Q9  | Website dark mode                                                                                      | §21.6 requires light/dark/system for the admin and the website. Figma only has light.                                                                                                                                                         | **Admin: full light/dark/system. Website: light only at launch**, with the dark toggle off. A website dark theme would be a new visual direction (REQ §31.1 says don't invent one).                                                                                                                                                                                   |
| Q10 | "Work & Examination" (§14) ✅ answered 2026-11-16: a module of the visa engine with its own workflow   | It's unclear what "Work" means (a task board? jobs for work-visa applicants?) and what kind of exams (IELTS/PTE language tests, or internal exams?).                                                                                          | **"Work" = internal task/work dashboard for counselor follow-ups; "Exams" = language/entrance tests assigned to students** (schedule, results). Please confirm.                                                                                                                                                                                                       |
| Q11 | Auth screens                                                                                           | Login/register styling: website style or admin style? One login for all roles?                                                                                                                                                                | **One login page in the website style** that redirects to the right dashboard by role.                                                                                                                                                                                                                                                                                |
| Q12 | Expenses ✅ answered 2026-11-16: yes, add them                                                         | §9 and §24 require an expense overview and income & expense reports, but no expense-entry feature is listed.                                                                                                                                  | **Add simple expense records** (category, amount, date, attachment) in Accounts. That's needed for the reports to work.                                                                                                                                                                                                                                               |
| Q13 | Partner portal                                                                                         | Partners have commission and payment history (§16), but no partner login is listed.                                                                                                                                                           | **No partner login.** Partners are managed by admins only.                                                                                                                                                                                                                                                                                                            |
| Q14 | Working Visa (§17) vs Visa Processing (§13) ✅ answered 2026-11-16: one engine, working visa as a type | Are these separate systems?                                                                                                                                                                                                                   | **One `applications` engine** with type (Study / Work / Visitor / Schooling / Skilled) and **configurable stage workflows per country + visa type**. Working Visa is a filtered view with its own categories, requirements and SEO pages.                                                                                                                             |
| Q15 | Brand name                                                                                             | Figma says "RTL Bangladesh" / rtlbangladesh.com. REQ says "RTL Immigration".                                                                                                                                                                  | **CMS setting**, seeded with the Figma values.                                                                                                                                                                                                                                                                                                                        |
| Q16 | Custom CSS/JS (§5, §21.6)                                                                              | Arbitrary JS injection is a security risk.                                                                                                                                                                                                    | **Super Admin only**, behind a dedicated permission, and audit-logged.                                                                                                                                                                                                                                                                                                |
| Q17 | Backup restore (§23.5)                                                                                 | Restoring a database from the browser is risky.                                                                                                                                                                                               | **Download and create backups from the UI.** Restore goes through a guarded, Super-Admin-only, confirmation-required flow, or is documented as a CLI-only operation.                                                                                                                                                                                                  |
| Q18 | Lead Manager role                                                                                      | It appears in §27 but has no dashboard spec.                                                                                                                                                                                                  | Uses the **admin panel**, with permissions limited to leads, contacts and tickets.                                                                                                                                                                                                                                                                                    |

---

## 6. Implementation phases

This follows REQ §29's 12 phases, with **three small adjustments** for dependencies (marked ⚠). Each phase ends with Pest tests, a lint/type-check/build pass and a short report.

### Phase 0: Foundation & design system

- Laravel 13 Vue starter kit, MySQL, Pest, Larastan, ESLint/Prettier, CI script.
- Tailwind v4 token files: `admin` (from `admin-demo.html`) and `website` (extracted from the Figma frames).
- Shared UI kit: DataTable (server-side sort/filter/paginate), FilterBar, SearchInput, StatusBadge, StatCard, Modal, ConfirmDialog, FormField set, FileUpload, EmptyState, Skeleton/loading, Toasts.
- Base layouts: Website, Admin, Counselor, Accounts, Portal (shells only).
- ⚠ **Cross-cutting infrastructure pulled forward:** settings store, activity log, notification plumbing and the numbering service. Every later phase depends on them, even though REQ lists notifications in Phase 12.

### Phase 1: Admin UI, dashboard, auth, roles & permissions (REQ §1, §2, §6, §25, §27)

- Login, password reset, email verification, 2FA, login history, rate limiting.
- Roles and permissions UI, plus the permission-driven dynamic sidebar (the full §25 structure, with modules added as phases land).
- Top bar: global search scaffold, notification centre, profile menu, light/dark/system theme.
- Super Admin dashboard shell (KPIs wired up as modules arrive).
- User management (CRUD, status, activity logs).

### Phase 2: Leads, users, students, counselors (REQ §7, §8, §10, §11.1–11.2)

- Lead capture API, ⚠ **plus a minimal working website contact form** so real leads flow in early. The full Figma website comes in Phase 9.
- Lead list, filters (country, service), status flow, assignment/transfer, follow-ups, notes, contact history, duplicate detection, convert to student.
- Counselor profiles and the student assignment table.
- Student accounts: self-registration, counselor-created accounts with an invitation and password setup, unique student number.
- Tickets and contact inquiries (§7, second half): categories, priority, assignment, internal notes, email reply, history.

### Phase 3: Student files & documents (REQ §10, §11.2)

- File opening with a unique file number, profile sections (personal / passport / education / contact).
- Document categories, private uploads, verification workflow, version history.
- File status and activity timeline.

### Phase 4: Three dashboards + payment request workflow (REQ §8.1, §9.1, §10.1, §12.1–12.2, §12.4)

- Counselor dashboard and its 20 menus (only the parts whose data exists; the rest are linked as later phases ship).
- Student portal dashboard and menus.
- Accounts dashboard shell.
- Dynamic payment types, payment requests with invoice generation, and the first file-opening invoice from file opening (§11 step 6).
- Manual payment evidence submission, then Accounts verify / confirm / reject. Status history, real-time sync to all three dashboards, and notifications.
- Rules enforced: counselors can't confirm their own requests, no deletion, idempotent confirmation, and a DB transaction around every transition.

### Phase 5: Accounts, invoices, refunds, gateways (REQ §9, §12.3, §12.5)

- Partial and due payments, outstanding balances, receipts (PDF), transactions, bank/gateway transaction views.
- Refund request → authorised approval → processing, referencing the original payment.
- Gateway driver (Q3) with server-side validation/IPN and webhook dedup. A browser redirect never marks a payment paid.
- Expenses (Q12), counselor-wise and student-wise collection, financial reports, PDF/Excel export.

### Phase 6: Visa processing & application tracking (REQ §13, §17, §14)

- Super-Admin-configurable workflows and stages per country + visa type.
- Stage updates with notes, documents and notifications. Full history record (previous/new status, by whom, when).
- Approved and rejected flows (decision document, rejection reason, office visit request and completion).
- Student-facing chronological timeline.
- Working Visa views: categories, requirements, required documents, fees, reports.
- Exams & Work management (scope per Q10).

### Phase 7: Tawk.to live chat (REQ §18)

- Widget on the website and portal, Secure Mode, visitor and custom attributes, webhook receiver with signature check, chat logs, admin settings, offline messages.
- Assignment, departments and tags depend on the Tawk.to plan and API, to be confirmed during the phase.

### Phase 8: Roundcube webmail (REQ §3, §19)

- Roundcube install and config (on the server), SSO plugin with signed short-lived tokens, account-to-user assignment, access permissions, activity logs.
- Laravel side: SMTP config, templates, signatures, bulk email (queued), delivery logs, per-student/counselor email history.
- Depends on Q1, Q2 and Q5.

### Phase 9: Blog, page CMS, dynamic website (REQ §4, §5, §21, §31.1)

- Pixel-faithful build of the 6 Figma pages as reusable section components, plus derived pages (§4.1 table) and responsive layouts.
- CMS: general settings, header, footer, dynamic menus (drag-and-drop, nested, role visibility), logos and branding, theme settings, homepage section manager (toggle, reorder), page builder (Q7), revisions and scheduled publishing.
- Blog: posts, categories, tags, featured, drafts, scheduling, comments moderation, authors.
- Universities (§15), partners (§16) admin + public pages, testimonials, FAQs, newsletter.
- ⚠ **University management moves here from "unassigned"**: REQ §29 doesn't place §15/§16 in any phase, and the website and service finder need them.

### Phase 10: SEO (REQ §22)

- Global SEO settings, per-entity SEO panel with SERP preview, OG/Twitter, JSON-LD (Organization, LocalBusiness, Website, Breadcrumb, Article), custom structured data.
- Dynamic XML sitemap, robots.txt editor, 301/302 redirects, 404 page, broken-link monitor (scheduled), hreflang (Q4), image alt text management, pagination SEO.
- Inertia SSR verified for all public pages.

### Phase 11: System settings, integrations, security, backup (REQ §23)

- Cache tools, maintenance mode (and scheduled), read-only system info (no secrets), log viewer.
- Security: password policy, login attempt limits, session management, IP restrictions, security notifications.
- Integrations: GA, GTM, Search Console, Bing, reCAPTCHA, Maps, Facebook Pixel, WhatsApp, webhook management. Secrets are encrypted at rest and never sent to Vue.
- Media library UI (categories, search, usage, allowed types, optimisation). Backups (Q17).

### Phase 12: Reports, notifications, testing & optimisation (REQ §20, §24, §31.5–31.6)

- All §24 reports with PDF/Excel/CSV export. Notification templates and settings, read/unread, per-role preferences.
- Full role-based access test matrix (Super Admin, Lead Manager, Counselor, Accounts, Student), plus responsive QA at desktop, tablet and mobile.
- Performance (N+1 checks, indexes, caching), accessibility pass, and a security review.

---

## 7. Requirement traceability (REQ section → phase)

| REQ §              | Phase                 | REQ §            | Phase                    |
| ------------------ | --------------------- | ---------------- | ------------------------ |
| 1 Top bar          | 1                     | 16 Partners      | 9                        |
| 2 Dashboard        | 1 (+ KPIs each phase) | 17 Working visa  | 6                        |
| 3 Email mgmt       | 8                     | 18 Tawk.to       | 7                        |
| 4 Blog             | 9                     | 19 Roundcube     | 8                        |
| 5 Pages CMS        | 9                     | 20 Notifications | 0 (infra) + 12           |
| 6 Users/RBAC       | 1                     | 21 Website CMS   | 9                        |
| 7 Leads/Tickets    | 2                     | 22 SEO           | 10                       |
| 8 Counselors       | 2 + 4                 | 23 System        | 0 (settings) + 11        |
| 9 Finance          | 4 + 5                 | 24 Reports       | 12 (+ per module)        |
| 10 Students        | 2 + 3 + 4             | 25 Sidebar       | 1                        |
| 11 End-to-end flow | 2–6                   | 26 Architecture  | 0 onward                 |
| 12 Payments        | 4 + 5                 | 27 Role rules    | 1 + enforced every phase |
| 13 Visa tracking   | 6                     | 28 Dev reqs      | throughout               |
| 14 Work/Exam       | 6                     | 31 Design/stack  | 0 + 9                    |
| 15 Universities    | 9                     |                  |                          |

---

## 8. Definition of done (every phase)

1. Server-side validation (FormRequests) and Policies on every protected action.
2. Pest feature tests for happy paths, permission denials and edge cases (duplicate webhooks, double confirmation, etc.).
3. `pint`, `larastan`, `eslint`, `vue-tsc` and `npm run build` all pass.
4. Loading, empty, validation-error and failure states on interactive screens.
5. Checked at desktop, tablet and mobile widths.
6. A report covering changed files, tests run, known limitations and next steps.
