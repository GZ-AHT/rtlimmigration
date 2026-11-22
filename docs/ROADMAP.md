# RTL Immigration IMS + CMS — Development Roadmap

**Status:** Approved 2026-11-15. **Phases 0–12 complete (Phase 12 closed 2026-11-22; Phases 9, 10 and 11 closed 2026-11-21):** 9a (content admin) and 9b (public website from Figma: CMS-rendered pages, derived pages, pathway finder, blog, universities, partner form, 404) done 2026-11-18. The website-style login (Q11) is done, and the FAQ, Blog and Partner pages were checked against their Figma frames on 2026-11-21 (FAQ page reworked: numbered topic groups, "Every journey is different" card, closing CTA; Blog and Partner already matched). Phase 9 is complete apart from the owner re-running `WebsiteContentSeeder` to see the new FAQ topics. Answered: **Q1** (Roundcube for mailboxes, Laravel for sending, templates, logs and SSO), **Q2** (cPanel mail host), **Q3** (SSLCommerz first, with Easy Checkout), **Q10** (Work & Examination is a module of the visa engine), **Q12** (yes, add expense records), **Q14** (one application engine for all visa types), **Q4** (English at launch, i18n-ready), **Q6** (video block is a marketing section), **Q7** (section builder + rich text), **Q8** (finder → university/course list), **Q9** (website light only), **Q11** (one website-style login), **Q13** (no partner login), **Q15** (brand name is a CMS setting), **Q16** (custom CSS/JS: Super Admin only, logged) and **Q5** (VPS), plus: students may request refunds. **Q17** (guarded restore from the admin panel) and **Q18** (Lead Manager uses the limited admin panel) were answered 2026-11-21.

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

**Phase 7 notes**

- Tawk.to stays the chat engine. Staff answer live chats in Tawk.to's own dashboard or app. This system shows the widget, signs students in to it, and keeps a record of every chat and offline message that Tawk.to's webhooks report.
- To set it up (Live Chat → Chat settings, Super Admin): paste the property and widget IDs from the Tawk.to widget link, the Secure Mode API key, and the webhook secret. In Tawk.to, create a webhook pointing at the address shown there, with Chat Start, Chat End, Chat Transcript and New Ticket ticked. Webhooks need a public HTTPS address, so nothing arrives on a local machine.
- The webhook address is editable per environment (local, staging, production) and saved encrypted. Each server shows the one for its `APP_ENV` (`production` → production, `staging` → staging, anything else → local), or its own `/chat/webhook/tawk` route when that one is blank. Every address must end with `/chat/webhook/tawk`. Staging and production must be public `https://`; local may use `http://`. The route and the signature check are unchanged.
- **Portal messaging (added 2026-11-17).** Tawk.to's public developer docs offer no API for sending agent replies (only the widget JS API, outgoing webhooks and Zapier), so replying from the dashboard is built into this system and never sent to Tawk.to. Each student has one continuous thread (`chat_conversations.kind = portal`, `external_id = student-{id}`), with messages in `chat_messages`, files in `chat_message_attachments` (private disk, documents type list) and read positions in `chat_reads` for unread counts. Each message carries a browser `client_id`, so a retried send is stored once. Clients poll every 5 s and only while the tab is visible; a poll with nothing new is answered from the conversation's `last_message_id`. `App\Events\ChatMessagePosted` already names its private channel, so moving to Reverb means implementing `ShouldBroadcast` and adding `routes/channels.php`. When a student's counselor changes, the thread moves with them and gets a note; history stays. Topics (Visa, Admission, Payment, Documents) reuse the tags column.
- **Chat security, moderation and website chat (added 2026-11-17, REQ §11).** Polling now uses a per-conversation change sequence (`chat_conversations.last_seq`, `chat_messages.seq`), so moderation changes reach open threads too. Every message passes `App\Support\Chat\Security\ChatGuard` (IP blocks, suspended guests, per-sender / per-IP / per-conversation limits, cooldowns, duplicate and repeated messages), `AttachmentInspector` (type from contents and signature, no scripts or embedded code, random storage names, optional ClamAV via `CHAT_MALWARE_SCANNER=clamav`) and, for students and visitors, `ContentInspector` (Blocked: scripts, blocked keywords and domains; Pending: several spam signs or a wall of links; Flagged: one sign, still delivered). Moderation statuses (`App\Enums\ModerationStatus`) never delete a message; history is in `chat_moderation_events`. Chat is text-only until Super Admin enables PDF / JPG / PNG. Website visitors chat at `/chat` (kind `guest`) with a random token in an encrypted HTTP-only cookie (hash stored in `chat_guests`), a honeypot, a signed form timer, a per-IP new-chat limit and Cloudflare Turnstile when configured; a signed-in student can link the chat to their account. Super Admin (`security.manage`) edits limits, lists, attachments and Turnstile keys at Admin → Live Chat → Chat security, and sees the security log, IP blocks and visitors; `chat.moderate` (Super Admin by default, grantable) opens the moderation queue. `chat:prune-security` runs daily for retention. Website chat is off by default.
- Every webhook must carry a valid `X-Tawk-Signature`. Without a saved secret, all webhooks are rejected. Rejected deliveries are logged without their content. Retries are recognised by `X-Hook-Event-Id` and processed once.
- Visitors on the website and signed-in students in the portal get the widget. Staff never do. A student's name, email, student number, file number and counselor are passed to Tawk.to, plus configurable tags (default `student`).
- With Secure Mode on (API key saved), a chat from a student's email is linked to their record and assigned to their counselor, who is notified. **Without Secure Mode, chats are never linked**, because anyone can type any email into the widget.
- Offline messages (Tawk.to "tickets") have their own list. They notify the student's counselor if known, otherwise everyone who can see all chats. Staff mark them handled once they have replied by email or phone.
- Who sees what: `chat.access` = chats assigned to you or from your students; `chat.view-all` = every chat, and can assign them (**Lead Manager has this by default**); `chat.manage` = settings and keys (Super Admin only by default).
- From any chat, staff can create a lead (source "Live chat"). A counselor's lead is assigned to them. This is REQ §18's visitor → chat → counselor → student path.
- Tags are kept in this system (up to 10, Tawk.to's limit). They are not pushed to Tawk.to, which has no server API for chat tags.
- Not built, because Tawk.to does not offer it to outside systems: assigning or transferring the live conversation inside Tawk.to (our "assigned to" only routes our record and notifications), departments (managed in Tawk.to), and online/offline status per agent. The portal shows whether anyone is online, as the widget reports it.
- Not checked against a real Tawk.to account yet: the widget and webhook formats follow Tawk.to's developer docs and are covered by tests.
- Also fixed: three small type errors (`easyCheckout.ts`, roles pages) that stopped `vue-tsc` passing.
- Fixed from the 2026-11-16 payment changes: two `GatewayPaymentTest` cases failed because a fail or cancel return now asks SSLCommerz whether the attempt was in fact paid, and the tests didn't fake that lookup. The lookup is intended (an Easy Checkout popup that closes after payment must not be recorded as failed), so only the tests changed. Two tests were added: a cancel claim that SSLCommerz shows as paid settles the invoice, and a cancel still records when SSLCommerz can't be reached.

**Phase 9b notes (public website, 2026-11-18)**

- **Every public page is a CMS page** rendered by `website/Page` from its sections (`App\Support\Cms\SectionHydrator` drops hidden sections, turns image ids into URLs and adds live records). `/` is the `home` page, `/contact` the `contact` page, and `/{slug}` (registered last in `routes/web.php`) any other page. The article page `/blog/{slug}` and university page `/universities/{slug}` have their own controllers. Drafts and scheduled pages return 404 to visitors; people with `pages.manage` see them with a preview bar. "Signed-in users only" pages send visitors to log in.
- **New section types** for the About page and derived pages: Page header, Story with list, Two panels (mission and vision), Numbered cards, Gallery and Message with portrait (24 types in total). The hero gained an accent heading line, finder tabs and a help link. The contact section gained its side panel. Testimonials, Latest articles and Universities gained an "all" mode for the reviews, blog and finder pages: full lists with filters, search and pages. Only one section per page reads the query string.
- **Pathway finder (Q8):** a tab without a link submits destination and study level to `/universities`, which lists matching universities with up to three matching courses each. A tab with a link (Schooling visa, Visitor visa, Skilled migration) opens that service page.
- **Built from Figma** (file `7XLQdh0vVNJdu3jjMQ9Dat`): the header, footer, WhatsApp and live chat buttons, and every homepage and About section. They were checked side by side with the Figma renders at 1440 px. The About page's photos came from one collage image in Figma; they were cut into separate images so each can be replaced in the media library. The Contact form reuses the Phase 2 Figma build (node 12:581).
- **Homepage checked against the full Figma export** (`RTL Bangladesh — Homepage.png`, 1440 × 5216, supplied by the owner). Section heights are within 10 px of the frame. "Normal" spacing follows Figma's uneven padding on desktop (`FIGMA_PADDING` in `SectionRenderer.vue`). Articles are tagged by destination, as in Figma. The footer copy and its Explore links match the frame. Arrows (↗ →) are drawn by `SiteArrow.vue`, because the bundled Inter is the Latin subset, which lacks those glyphs.
- **Contact page rebuilt from the Figma export** (`RTL Bangladesh — Contact.png`, supplied by the owner). It has three sections: "Page header with contact cards" (`contact_hero`: two-tone heading, photo with caption, call / email / WhatsApp cards whose details come from Site settings), the existing enquiry form, and "Visit us with map" (`visit`: address, an OpenStreetMap embed from latitude and longitude, a Google Maps link and visit tips). Text positions are within 1–3 px of the export. A new spacing option, "Joined to the section above", lets sections on the same background run on as in the design. The seeder upgrades a form-only Contact page automatically but leaves an edited one alone. The map pin is approximate (Gulshan 1); set the exact office coordinates in the CMS. The map loads tiles from openstreetmap.org.
- **Blog page rebuilt from the Figma export** (`RTL Bangladesh — Blog.png`). It has a "Simple page header" (`page_intro`, with a red note on the right), the full listing (the newest featured article as an "Editor's pick" card, then "Explore the journal" with search, category pills, bordered article cards, "Showing N articles" and numbered pages) and the Figma newsletter band (the button sits inside the email box, with small print under it). Section heights are within 2 px of the export. Cards everywhere now show the article's first tag (e.g. a destination) or else its category. The seeded categories are the Figma pills (Study destinations, Visa guidance, Student life, Planning your journey), and the seeded articles include the Figma Editor's pick and three more. The homepage "Latest insights" section can be limited to one category; the homepage uses Study destinations, so it still shows the three destination articles from the homepage design. The seeder switches an unchanged first-version blog page to the new layout. One known difference: Figma shows no date on its last three cards, but every card here shows one.
- **Two homepage banner designs (owner's request, 2026-11-18).** Design 1, "Existing banner" (`hero`), is unchanged: with default settings it is pixel-identical to before. Design 2, "New full-width banner" (`hero_cover`), is built from the owner's reference `Hero and service finder.png`; its text lands on the same pixel rows at 1440 px. Each design is its own section with its own content and settings, built from one shared field set (`$banner` in `SectionTypes`): heading, buttons, image or video background, fallback image, overlay style and strength, text alignment, banner height, video on phones, and the pathway finder. The shared website parts are `BannerMedia.vue` and `PathwayFinder.vue`. **Website CMS → Homepage → Banner design** picks the live design. Choosing one hides the other's section without touching its content, and saving publishes the choice. **Preview** opens `/?banner=hero_cover` (or `hero`): editors see that design as last saved, with a notice bar and noindex, and visitors' links ignore the parameter. The website renders one banner even if both are switched on. Video: autoplay, muted, loop, playsinline. It is added after the page loads with the image as poster and fallback, pauses off-screen, is skipped for visitors who prefer reduced motion, and on phones unless "Play the video on phones" is ticked. **The media library accepts MP4 and WebM videos up to 50 MB** (types checked from the contents). Image fields accept only images and video fields only videos. Production note: PHP `upload_max_filesize` / `post_max_size` and Nginx `client_max_body_size` must allow 50 MB. The seeder adds Design 2, hidden, to existing homepages. The admin Banner design card has not been checked in a browser.
- **Video counseling call window (owner's request):** shows the adviser as a photo or a muted, looping video (`media_type`, `video`, optional on phones; same playback rules as the banners). It has two styles. "Video call" (the default) looks like Google Meet / Zoom: a live dot with a running timer, a speaking outline with sound bars on the adviser's name, an HD badge, a self-view tile (optional photo), and round call controls with a red end-call button. "Simple" is the Figma design. Editable: adviser name, self-view photo and timer on/off. Component: `CallWindow.vue`. Checked in a browser with a photo and with a video, at desktop and phone widths.
- **Partner application page rebuilt from the owner's export** (`RTL Bangladesh — Partner application.png`). The page has three parts:
    - "Simple page header" with a parent breadcrumb (Home / Partners / Partner application) and a panel with a red edge.
    - The form in four numbered groups (Organisation details, Primary contact, Your partnership, Supporting documents), next to a sidebar: a dark benefits panel, a "Have a question first?" box (email and phone from Site settings) and "Before you apply" tips.
    - A new "Numbered steps" section (`steps`) for "What happens next".
    - Text rows are within 1–3 px of the export, and the sidebar is exact.
    - New questions, stored in 4 new columns (migration `2026_11_18_070000`): job title, partnership interest (`PartnerApplication::INTERESTS`), years in operation, and preferred study destinations (a checkbox dropdown; destination countries only).
    - Country and city are now required, and documents may be up to 10 MB.
    - The admin application page shows the new answers.
    - The seeder switches an unchanged first-version partner page to the new layout.
    - Checked in a browser: a full submission with destinations and a PDF reaches the admin page.
- **Not yet checked against Figma:** FAQ (`12:675`). Figma's Starter-plan MCP limit was reached during this phase, so those pages use the homepage FAQ, article card and Contact form designs. Compare them with Figma once the limit resets.
- **Derived pages (no Figma frame):** blog article, university profile, reviews, universities, five service pages (student, schooling, visitor, skilled migration, work permits), privacy, terms, cookie policy, and the 404/403/429/500/503 pages. **Service and legal page copy is placeholder text** for the owner to replace. The legal pages say so on the page.
- **Seeding:** `WebsiteContentSeeder` (part of `db:seed`) copies the Figma photos from `public/images/site` into the media library, then creates 16 pages, the menus, FAQs, one testimonial and the three Figma articles. It never overwrites anything that already exists. The heavy Figma PNGs were resized to 1600 px JPEGs (3.9 MB down to about 0.2 MB).
- **Menus** are cached for 10 minutes, and the cache is cleared whenever a menu, menu item or page is saved. A link to an unpublished page is hidden. Visibility by visitors, signed-in users or roles is applied per request.
- **Theme settings now apply:** primary and secondary colour, heading and body fonts (fonts not bundled load from Bunny Fonts), button corners, favicon and logos (an uploaded logo replaces the RTL wordmark). The CMS defaults now reach the website too: before this, header and footer settings nobody had saved were blank on the site.
- **Light only (Q9):** website pages never get the `dark` class, whatever the visitor's panel appearance. Moving between the website and the panels in the same tab re-applies the right theme.
- **Custom code (Q16):** site-wide CSS, head code and body code are output on website pages only, never on admin or portal pages. `</style` is stripped from CSS. Per-page CSS/JS is added while that page is open.
- **Forms:** partner applications (with up to 3 PDF/JPG/PNG documents of 10 MB each, stored privately; one pending application per email; reviewers and Super Admins are notified), blog comments (held for moderation, with replies), and newsletter sign-up and one-click unsubscribe (`/newsletter/unsubscribe/{token}`). All of them have honeypots and rate limits.
- **SEO basics** on every public page: title, description, canonical, Open Graph and Twitter tags, and noindex for previews and non-public pages. The rest of SEO is Phase 10.
- **Responsive:** checked at 1440 and 390 px. Below 1280 px the nav becomes a slide-in menu. Tablet widths and the mobile menu's open state have not been checked by eye.
- The website-style auth screens (Q11) are complete: login, registration, password reset, 2FA, email verification and password confirmation all use the website layout and Figma styling.
- Tests: `tests/Feature/Cms/PublicWebsiteTest.php` (28 tests).

**Phase 11 notes (system settings, security, integrations, backups, 2026-11-21, REQ §23)**

- Answered 2026-11-21: **Q17** = guarded restore from the browser (Super Admin only, own password, typed `RESTORE`, safety backup first); **Q18** = Lead Manager keeps using the admin panel with permissions limited to leads, contacts and tickets (already how the seeded role works; no new dashboard).
- **System → System Settings** (`SystemController`, `App\Support\System\SystemSettings`): Overview (read-only versions, drivers, database name and server version, upload limits, storage use; never passwords or hosts), Maintenance, Cache tools (application, route, view, config), Logs (newest entries of the newest `storage/logs/*.log`, filter by level). Security and Integrations are tabs on the same screen; each tab needs its own permission (`settings.manage`, `security.manage`, `integrations.manage`). Settings changes are logged without their values. Secret fields are encrypted at rest and only ever reported as "saved".
- **Maintenance mode** is its own switch plus an optional start and end time (`EnforceMaintenance`), not `artisan down`. Visitors get the 503 website page with your message; people with `settings.manage`, listed IPs, the sign-in pages, payment-gateway callbacks and chat webhooks still get through.
- **Security:** password policy (length, mixed case, numbers, symbols, leak check; until Super Admin saves one, production keeps the old strict rule), sign-in attempt limit and lockout length, idle session time, a staff-area IP allow list (admin, counselor and accounts URLs) and a whole-site IP block list (`EnforceIpRules`), and notifications to Super Admins and `security.manage` holders on lockouts and on staff signing in from a new address (`SecurityAlerts`). Careful: a wrong allow list locks staff out; fix it by clearing the `security.admin_ip_allowlist` setting in the database. **System → Audit logs** shows admin activity and sign-in history (`audit.view`).
- **Integrations:** Facebook Pixel ID, Google reCAPTCHA v3 on the contact, partner, newsletter, comment and assessment forms (a missing or low-scoring token is refused; if Google can't be reached the form still works), WhatsApp on/off and opening message. Analytics, Tag Manager and verification codes are in Site settings → SEO. Payment gateways, email and live chat keep their own screens. **Webhooks** (System → Integrations → Webhooks): HTTPS addresses only, never private-network addresses (checked on save and again at delivery), events `lead.created`, `student.created`, `application.status_changed`, `payment.status_changed`, `partner.application_received` (ids and statuses only, no personal details), HMAC-SHA256 signature (`X-RTL-Signature`, secret shown once), 3 tries, delivery log, switched off after 25 failures in a row. Needs the queue worker (Supervisor) to be running.
- **Backups** (System Settings → Backups, `backups.manage`): database backup (gzipped SQL written by `BackupService`, MySQL only, one statement per line, `backups`, sessions, cache and job data left out) and file backup (zip of `storage/app/public` and `storage/app/private`). Create, download, delete, automatic schedule (off, nightly or Sundays at 2:00) with retention (`php artisan backup:run --scheduled`). Backups sit in `storage/app/backups` (outside the web root). Restore is database-only and runs only statements the backup writer produces; restoring files means unzipping the archive by hand. Restoring replaces every table in the backup, so staff may be signed out afterwards.
- Not done from the Phase 11 list: media library categories, usage tracking and image optimisation (the library already has search, alt text with a missing-alt filter, delete and type limits), Google Maps key (the contact page keeps OpenStreetMap plus a Google Maps link), a "Third-party API" key store, and scheduled-backup copies to remote storage (download them to keep an off-server copy).
- Needs `php artisan migrate`, the scheduler (`schedule:run`) and the queue worker on the server.
- Tests: `tests/Feature/System/SystemSettingsTest.php` (14) and `tests/Feature/System/WebhooksAndBackupsTest.php` (11).

**Phase 10 notes (SEO, 2026-11-21, REQ §22)**

- **Global SEO** is a new tab at Website CMS → Site settings → SEO (`App\Support\Cms\SiteSettings`, group `seo`): on/off switch, hide-the-whole-site switch (noindex + `Disallow: /`), default title / description / keywords / robots, default canonical address, Open Graph and Twitter defaults, publisher, Organization / WebSite / LocalBusiness structured data, hreflang, Search Console and Bing verification, Google Analytics and Tag Manager IDs (format-checked, and only then written into the page), sitemap on/off with extra addresses, and a robots.txt editor.
- **Per-page SEO** (`seo_meta`, shown in the SEO panel on pages, posts and universities): added a use-these-fields switch, keywords, nofollow, Twitter card and custom JSON-LD (validated as JSON). Focus keyword, canonical, Open Graph and noindex already existed. Turning a record's SEO off makes it use the site defaults.
- `App\Support\Cms\WebsiteSeo::make()` now builds title, description, canonical, robots, Open Graph, Twitter, hreflang and JSON-LD. Breadcrumb data is on every inner page, Article data on blog posts, Organization / WebSite / LocalBusiness on the homepage. JSON-LD and analytics are written by `resources/views/app.blade.php` (so crawlers get them without running JavaScript); the rest goes through `SeoHead.vue`.
- **Technical SEO:** `/sitemap.xml` (live public pages, posts, active universities, published assessments, extra addresses; `noindex` records left out) and `/robots.txt` are dynamic (`SeoController`); `public/robots.txt` was removed so the route answers. Redirects (301 / 302) live at SEO Management → Redirects, run as global middleware so they work for paths no page matches, count hits, and refuse loops, duplicates and system paths. The broken-link monitor (`seo:check-links`, weekly on Monday 03:30, or Scan now) checks links in page sections, posts and menus (400 addresses at most per scan) and lists them at SEO Management → Broken links. The media library has a Missing alt text filter. Page 2 and later of a list get their own canonical (`?page=N`).
- Needs the scheduler (`schedule:run`) for the weekly scan. Run `php artisan migrate` on the dev DB (adds `seo_meta` columns, `redirects`, `broken_links`).
- Not done: separate sitemap files per type, a 404 log, and a second language (hreflang only marks English until Q4 adds Bangla). Server-side rendering is not enabled in this project (`config/inertia.php` ssr bundle is off), so the verified path is the server-rendered `<head>` and JSON-LD from the Blade view; the Inertia `<Head>` tags appear once the page loads.
- Tests: `tests/Feature/Cms/SeoManagementTest.php` (15 tests).

**Phase 9a notes (content admin, 2026-11-18)**

- New admin modules: Blog (posts, categories, tags, comments, authors), Pages (section builder), Website CMS (homepage, site settings, menus, FAQs, testimonials, newsletter), Universities (with courses and countries), Partners (applications, profiles, documents, commission, payments) and the Media library. Phase 11 was the plan for the media library; it was pulled forward because every editor needs images.
- **Page builder (Q7):** a page is a list of sections. The 18 section types live in `App\Support\Cms\SectionTypes`, matched to the Figma homepage sections plus the REQ §21.7 extras. The admin form is generated from those definitions. `SectionSanitizer` re-checks everything on save: unknown types and fields are dropped, text is plain and length-limited, rich text is cleaned, links must be site paths, `#anchors`, `http(s)`, `mailto:` or `tel:`, and images must exist. Each section can be shown or hidden, duplicated, reordered by drag-and-drop or buttons, and given a background, spacing, anchor and "hide on phones". The homepage is the built-in `home` page, so Website CMS → Homepage opens the same editor (REQ §21.7 section manager). Built-in pages keep their address and can't be deleted.
- **Rich text:** TipTap 3 in the browser (TipTap 2 had a published advisory, GHSA-cp6q-959q-f8rh). HTML is cleaned server-side by `symfony/html-sanitizer` (`App\Support\Content\RichText`): no scripts, styles, event handlers, iframes or `javascript:` links.
- **Publishing:** posts and pages are Draft or Published. A published item with a future date is shown as Scheduled and goes live on its own when the date passes, with no queue job. People with `blog.manage` / `pages.manage` but not `*.publish` can only save drafts. Page saves keep up to 30 revisions; restoring one keeps the replaced version too.
- **Custom code (Q16):** site-wide (Site settings → Custom code) and per-page CSS/JS need `cms.custom-code` (Super Admin only by default). Every change is logged under `security`. Site-wide code is never part of the public settings payload; `</style` is stripped from CSS.
- **SEO fields** (meta title and description, focus keyword, canonical, Open Graph title, description and image, noindex) are on posts, pages and universities (`seo_meta`, polymorphic). Global and technical SEO, sitemaps and schema are Phase 10.
- **Media:** JPG, PNG, WEBP and GIF up to 5 MB on the public disk under random names. SVG is refused because it can carry scripts. Anyone who edits posts, pages, CMS or universities can upload from the picker. Deleting an image leaves pages that used it without that image.
- **Menus:** locations main, top, mobile, footer (Explore), footer services and footer legal. Links go to a page or a safe custom URL, can open in a new tab, nest one level (dropdown), and can be shown to everyone, visitors, signed-in users or chosen roles.
- **Partners (Q13):** there is no partner login. Approving an application creates partner `PTR-0001…` and moves its documents over. Rejecting needs a reason. Documents are private and served with `nosniff` and a sandbox CSP.
- **Site settings:** General, Header, Footer, Social, Logo and branding, Theme, Custom code, defined in `App\Support\Cms\SiteSettings` and seeded from Figma values. The website is light-only (Q9).
- The built-in pages, Figma copy and default menus are seeded by `WebsiteContentSeeder` (9b, below).
- All 23 admin screens open in tests (`ContentManagementTest`), and server-side behaviour is covered there. The admin screens themselves have not been checked in a browser yet.

**Phase 8 notes**

- Mailboxes live on the cPanel mail server (Q2) and are read in **cPanel's own Roundcube**. No separate Roundcube is installed on the VPS (Q5). This system manages the accounts, signs staff in to webmail, and sends, templates and logs mail (Q1).
- **Set-up (Email / Roundcube → Email settings, `email.manage`):** cPanel server, port (2083), username and an API token (cPanel → Security → Manage API Tokens), plus the mail domains, a default storage limit, and the SMTP and IMAP servers (blank means the cPanel server). The token is encrypted and never returned to the browser. "Test saved connection" lists the domains cPanel reports.
- **Single sign-on (REQ §19.3):** "Open webmail" asks cPanel's UAPI `Session::create_webmail_session_for_mail_user` for a one-time session. A small page then posts it to `https://<server>:2096/cpsessXXXX/login/` with `goto_uri=/3rdparty/roundcube/`, in a new tab. No mailbox password reaches the browser. Only users assigned to the mailbox can do this, and every sign-in is logged.
- **Accounts:** creating, suspending, resuming, deleting, changing the password and changing the storage limit happen on the cPanel server first (UAPI `Email::add_pop`, `suspend_login`, `delete_pop`, `passwd_pop`, `edit_pop_quota`) and are recorded only if cPanel accepts. Deleting asks whether to erase the mailbox on the server or only remove our record. Without a cPanel connection, accounts are recorded only and can be created on the server later ("Create on server"). "Sync from cPanel" imports mailboxes made in cPanel directly, plus usage and suspensions. Imported mailboxes have no saved password: they work in webmail but can't send from this system until one is saved.
- Mailbox passwords are generated (24 characters) unless typed, stored encrypted, and used only for SMTP when sending from the system.
- **Access:** each mailbox has assigned users. "Can send" also lets them choose it as From in the system. `email.access` gives "My email" (admin, counselor, accounts, and the portal for students an admin grants it, per REQ §10.1). `email.manage` gives accounts, templates, bulk email, logs and settings (Super Admin only by default).
- **Sending from the system (REQ §3):** compose with a template, placeholders (`{{ name }}`, `{{ first_name }}`, `{{ student_number }}`, `{{ file_number }}`, `{{ counselor_name }}`, `{{ sender_name }}`, `{{ company_name }}`…), the user's signature and up to 5 attachments (document types and size limit). Bodies are plain text: they are escaped, links are made clickable and line breaks kept, so nobody can inject HTML. Each mailbox sends through its own SMTP login. The "System address" option uses the app's default mailer. Everything goes through the queue (`SendEmailMessage`, 3 tries) and lands in `email_messages`.
- A counselor can only email students and leads they can see. A typed address that matches a visible student or lead is linked to them, so it shows in their history.
- **Bulk email:** students (by counselor and file status) or leads (by counselor and status), one personalised email each, de-duplicated by address, capped at 2,000 per send. Campaign progress (sent and failed) is kept, and the log filters by campaign.
- **Delivery logs:** status, error, preview (shown in a sandboxed frame) and retry for failed emails. **Tracking:** an open pixel and signed click redirects (unsigned or altered links are refused, so the redirect can't be abused). It can be switched off in settings. Opens are a lower bound because many mail apps block images.
- **History:** a student's page has an Emails tab (REQ §3 Student Email History). A counselor's admin page shows mail sent by or to them (Counselor Email History). Mail sent from Roundcube is not in these: it stays in the mailbox.
- Email activity (account changes, access changes, webmail sign-ins, settings, bulk sends) is in the activity log under `email` and on the Email accounts page (REQ §19.2 "Email Activity Logs").
- **Email gaps closed (2026-11-23, after an audit that kept Q1 as decided):** Compose has Cc and Bcc (up to 10 addresses each, validated, repeats dropped; Bcc is in the SMTP envelope but not in the message headers). "Sent from the system" has Reply (Re:, quoted original, same student/lead link), Forward (Fwd:, attachments copied to the new email) and Delete. Delete only hides the dashboard copy (`email_messages.trashed_at`): the delivery log, the person's history, reports and the real mailbox are untouched, and it can be restored from the Deleted tab. Emails still queued cannot be deleted. `SyncMailboxes` (queued, unique) runs the cPanel mailbox sync every 30 minutes through the scheduler; the manual Sync button stays. Needs the scheduler and a queue worker.
- Not built: auto-replies, folders, labels and the address book are Roundcube and cPanel features, so staff use them there. There is no unsubscribe link in bulk email, because the audience is limited to the agency's own students and leads; add one before any marketing use.
- Not checked against a real cPanel server yet. The UAPI calls follow cPanel's API docs and are faked in tests. Check SSO and creating a mailbox once credentials are available. If a server has a self-signed certificate, untick "Check the server's SSL certificate".
- A queue worker must run in production (`php artisan queue:work`, under Supervisor per Q5), or emails stay queued.

**Tawk.to switched off (2026-11-17, owner's decision)**

- The system's own chat replaces Tawk.to: portal messages for students, and website chat for visitors. This departs from REQ §18, which names Tawk.to.
- The integration is kept but off: `TAWK_ENABLED=false` (default, `config/chat.php`). While it's off there is no widget, the webhook returns 404, and the Tawk.to settings, chat history and offline messages screens and menus are hidden. Set `TAWK_ENABLED=true` to bring it all back.
- **Website chat popup:** a "Chat with us" button on every public website page opens the visitor's chat in a panel (full screen on phones). It uses the same chat, cookie and security checks as `/chat`. The panel loads its state from `GET /chat/widget` only when opened and polls only while open. It stays open across page changes in the same tab. It's hidden on `/chat` itself, for staff, and whenever website chat is off (Admin → Live Chat → Chat security).

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
| Login / Register / Forgot password / 2FA    | ✅          | Derived from the website style (Q11 complete)                                 |
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
| Q1  | **Email: §3 vs §19** ✅ answered 2026-11-17: Roundcube + Laravel split as recommended                  | §3 lists a full in-app mail client (Inbox, Sent, Spam…). §19 says to use **Roundcube** as the webmail interface. Building both would duplicate work.                                                                                          | **Roundcube handles mailboxes** (Inbox, Sent, Drafts, Spam, Trash, Archive, Search, Address book). **Laravel handles** SMTP config, templates, signatures, bulk email, transactional notifications, delivery logs, student/counselor email history, account assignment and permissions. "Email Tracking (if supported)" means opens/clicks on Laravel-sent mail only. |
| Q2  | Mail server ✅ answered 2026-11-17: cPanel                                                             | Roundcube needs an IMAP/SMTP server and mailbox accounts. Who provides these (cPanel hosting, Google Workspace, a self-hosted server)? "Create Email Account" from the admin panel only works if the mail host has an API (e.g. cPanel UAPI). | **Tell me the mail host.** Until then, account creation in the admin panel records assignments only.                                                                                                                                                                                                                                                                  |
| Q3  | Payment gateway ✅ answered 2026-11-16: SSLCommerz first                                               | §12.3 names SSLCommerz, aamarPay, bKash and Nagad. Do you have merchant accounts? Which comes first?                                                                                                                                          | **SSLCommerz first** (it covers cards, bKash, Nagad and bank in one), plus manual bank/cash entry with evidence upload. Other gateways added as drivers later.                                                                                                                                                                                                        |
| Q4  | Languages ✅ answered 2026-11-17: English at launch, i18n-ready                                        | §21.1 / §22.3 mention website language, a default language and hreflang. Is Bangla content required at launch?                                                                                                                                | **English at launch, i18n-ready.** Add Bangla when content exists.                                                                                                                                                                                                                                                                                                    |
| Q5  | Hosting ✅ answered 2026-11-17: VPS                                                                    | Reverb (real-time), queue workers, the Roundcube install, scheduled backups and Inertia SSR all need a **VPS** (or similar). Shared hosting limits these.                                                                                     | **VPS with Nginx, PHP-FPM, MySQL, Redis and Supervisor.** What's the target environment?                                                                                                                                                                                                                                                                              |
| Q6  | Video counseling (Figma) ✅ answered 2026-11-17: marketing section, CTA opens the enquiry form         | Figma shows a video room UI ("RTL video room", mic/camera/end-call). REQ has **no** video or appointment-booking module.                                                                                                                      | **Treat it as a marketing section** whose CTA opens the consultation enquiry form, with "video" as the contact preference. A real booking or video system would be a new module and needs your approval.                                                                                                                                                              |
| Q7  | Page builder ✅ answered 2026-11-17: sections + rich text                                              | "Page Builder / Rich Text Editor" could mean a visual drag-and-drop builder or a rich text editor.                                                                                                                                            | **Section-based builder** (pick from Figma-styled sections, edit fields, reorder, show/hide) **plus TipTap** for rich text blocks. No free-form pixel builder.                                                                                                                                                                                                        |
| Q8  | Service finder (Figma) ✅ answered 2026-11-17: filtered university/course list                         | The homepage visa tabs + destination + study level → "Find my pathway". What should the result be?                                                                                                                                            | **A filtered University/Course listing page.** Needs the University module (Phase 9 dependency).                                                                                                                                                                                                                                                                      |
| Q9  | Website dark mode ✅ answered 2026-11-17: website light only                                           | §21.6 requires light/dark/system for the admin and the website. Figma only has light.                                                                                                                                                         | **Admin: full light/dark/system. Website: light only at launch**, with the dark toggle off. A website dark theme would be a new visual direction (REQ §31.1 says don't invent one).                                                                                                                                                                                   |
| Q10 | "Work & Examination" (§14) ✅ answered 2026-11-16: a module of the visa engine with its own workflow   | It's unclear what "Work" means (a task board? jobs for work-visa applicants?) and what kind of exams (IELTS/PTE language tests, or internal exams?).                                                                                          | **"Work" = internal task/work dashboard for counselor follow-ups; "Exams" = language/entrance tests assigned to students** (schedule, results). Please confirm.                                                                                                                                                                                                       |
| Q11 | Auth screens ✅ answered 2026-11-17: one website-style login                                           | Login/register styling: website style or admin style? One login for all roles?                                                                                                                                                                | **One login page in the website style** that redirects to the right dashboard by role.                                                                                                                                                                                                                                                                                |
| Q12 | Expenses ✅ answered 2026-11-16: yes, add them                                                         | §9 and §24 require an expense overview and income & expense reports, but no expense-entry feature is listed.                                                                                                                                  | **Add simple expense records** (category, amount, date, attachment) in Accounts. That's needed for the reports to work.                                                                                                                                                                                                                                               |
| Q13 | Partner portal ✅ answered 2026-11-17: no partner login                                                | Partners have commission and payment history (§16), but no partner login is listed.                                                                                                                                                           | **No partner login.** Partners are managed by admins only.                                                                                                                                                                                                                                                                                                            |
| Q14 | Working Visa (§17) vs Visa Processing (§13) ✅ answered 2026-11-16: one engine, working visa as a type | Are these separate systems?                                                                                                                                                                                                                   | **One `applications` engine** with type (Study / Work / Visitor / Schooling / Skilled) and **configurable stage workflows per country + visa type**. Working Visa is a filtered view with its own categories, requirements and SEO pages.                                                                                                                             |
| Q15 | Brand name ✅ answered 2026-11-17: CMS setting, Figma value first                                      | Figma says "RTL Bangladesh" / rtlbangladesh.com. REQ says "RTL Immigration".                                                                                                                                                                  | **CMS setting**, seeded with the Figma values.                                                                                                                                                                                                                                                                                                                        |
| Q16 | Custom CSS/JS (§5, §21.6) ✅ answered 2026-11-17: Super Admin only, audit-logged                       | Arbitrary JS injection is a security risk.                                                                                                                                                                                                    | **Super Admin only**, behind a dedicated permission, and audit-logged.                                                                                                                                                                                                                                                                                                |
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
- Inertia SSR verified for all public pages (see the Phase 10 notes: SSR is off here, so this covers the server-rendered head and JSON-LD).

### Phase 11: System settings, integrations, security, backup (REQ §23)

- Cache tools, maintenance mode (and scheduled), read-only system info (no secrets), log viewer.
- Security: password policy, login attempt limits, session management, IP restrictions, security notifications.
- Integrations: GA, GTM, Search Console, Bing, reCAPTCHA, Maps, Facebook Pixel, WhatsApp, webhook management. Secrets are encrypted at rest and never sent to Vue.
- Media library UI (categories, search, usage, allowed types, optimisation). Backups (Q17).

### Phase 12: Reports, notifications, testing & optimisation (REQ §20, §24, §31.5–31.6)

- All §24 reports with PDF/Excel/CSV export. Notification templates and settings, read/unread, per-role preferences.
- Full role-based access test matrix (Super Admin, Lead Manager, Counselor, Accounts, Student), plus responsive QA at desktop, tablet and mobile.
- Performance (N+1 checks, indexes, caching), accessibility pass, and a security review.

**Phase 12 notes (built 2026-11-22):**

- **Reports hub** (`/admin|counselor|accounts/reports`): one screen and one exporter (`OperationalReports`, `OperationalReportController`) serve nine reports: student registrations, counselor performance, university applications, visa applications, partner applications, email activity, tickets, live chat and website traffic, next to the Phase 5 finance reports. Each report is gated by the permission of its data (e.g. `tickets.view`, `chat.view-all`, `seo.manage` for traffic); registrations and applications only count records the viewer may see (a counselor sees their own students). Exports (Excel, CSV, PDF) need `reports.export`, are throttled and logged, and escape spreadsheet formulas. The screen shows the first 100 rows; downloads carry every row.
- **Website traffic** is new data: `TrackPageView` middleware records one row per page, day and anonymous visitor in `page_views` (daily-rotating hash, no cookie, no IP stored; bots, staff, prefetches and non-pages skipped; pruned after 430 days by the scheduler).
- **Notifications:** the centre filters by category (System, Email, Student, Counselor, Payment, Application) with unread counts. Each person sets their own in-app and email choices per category; an admin (`notifications.manage`) sets role defaults and each notification type's channels and email wording at Admin → Notifications → Templates & settings. Precedence: own choice, then role default, then in-app only. Security alerts always reach the in-app centre. Email copies go through `SafeMailChannel`, so a mail failure never breaks the action that caused it. The bell refreshes every 45 seconds while the tab is visible (polling, not websockets). `NotificationCatalog` lists every notification type; a test fails if a new one is not added. The Email category has no senders yet.
- **Testing and review:** a role access matrix (`RoleAccessMatrixTest`) requests every parameter-free dashboard page as each of the five roles and checks area isolation, signed-out redirects and no 5xx. It found that `/admin/users/create` was swallowed by `users/{user}` (404 for everyone); fixed with `whereNumber`. Indexes added on the dates the reports filter by; a test keeps report query counts flat as data grows. New screens checked headless at 360, 768 and 1280px: no console errors, no overflow, every control labelled. Not done: a manual screen-reader pass and a penetration test.
- **Also in this session:** mobile homepage pass, smart glass header on phones, four-action phone bar with live chat, video consultation moved above the counter (migration `2026_11_22_000000`).

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
