# RTL Immigration -- Complete Admin Dashboard & Immigration Management System Requirements

**Document purpose:** Consolidated requirements for the RTL Immigration
website, Super Admin panel, client portal, counselor workspace, accounts
dashboard, website CMS, SEO, live chat and webmail integrations.

**Primary objective:** Build a centralized Immigration Management System
(IMS) with a dynamic CMS. Super Admin controls the complete platform,
while Users/Students, Counselors and Accounts staff work through
separate role-based dashboards connected to one centralized Laravel
application and database.

------------------------------------------------------------------------

# 1. Admin Panel -- Top Bar & General

-   Super Admin title and branding
-   Global search
-   Notifications icon and notification center
-   User profile menu
-   Dashboard overview
-   General system settings
-   Responsive admin layout
-   Dynamic sidebar based on role and permissions

# 2. Dashboard Overview

-   Total Students
-   Total Applications
-   Total Counselors
-   Total Universities
-   Total Partners
-   Total Payments
-   Total Pending Bills
-   Total Tickets
-   Recent Activities
-   Recent Student Registrations
-   Recent Payments
-   Application Statistics
-   Monthly Revenue Chart
-   Pending Tasks and Notifications
-   Quick Actions

# 3. Email Management

-   Inbox
-   Sent
-   Compose Email
-   Drafts
-   Spam
-   Trash
-   Archive
-   Email Configuration
-   Email Accounts Management
-   Email Templates
-   Email Signature
-   Email Categories / Labels
-   Bulk Email
-   Student Email History
-   Counselor Email History
-   Email Attachments
-   SMTP Configuration
-   Email Delivery Logs
-   Email Notifications
-   Auto Reply
-   Email Tracking (if supported)

# 4. Blog Management

-   All Blogs
-   Create New Blog
-   Edit Blog
-   Blog Categories
-   Blog Tags
-   Featured Blogs
-   Drafts
-   Published Blogs
-   Scheduled Publishing
-   Featured Image
-   Blog SEO Settings
-   Meta Title
-   Meta Description
-   Focus Keywords
-   Open Graph Settings
-   Schema Markup
-   Blog Comments
-   Author Management

# 5. Page Management (CMS)

-   All Pages
-   Create New Page
-   Edit Page
-   Delete Page
-   Page Builder / Rich Text Editor
-   Page Categories
-   Page Status
-   Draft / Published
-   Page SEO
-   Meta Title and Description
-   Canonical URL
-   Open Graph Settings
-   Schema Markup
-   Custom CSS / JS
-   Page Visibility
-   Page Revision History
-   Scheduled Publishing

# 6. User Management

-   All Users
-   Create New User
-   Edit User
-   Delete User
-   User Profile
-   User Status
-   Active / Inactive Users
-   Email Verification
-   Password Reset
-   Role Management
-   Permission Management
-   Role-Based Access Control (RBAC)
-   Login History
-   User Activity Logs

# 7. Lead & Contact Management

-   Website Lead Form
-   All Leads
-   New Leads
-   Pending Leads
-   Lead Sorting
-   Country-Based Filtering
-   Service-Based Filtering
-   Lead Assignment
-   Counselor Assignment
-   Lead Transfer
-   Follow-up Date
-   Contact History
-   Lead Notes
-   Lead Status
-   Convert Lead to Student
-   Duplicate Lead Detection
-   All Contact Inquiries
-   All Support Tickets
-   Create Ticket
-   Ticket Details
-   Assign Ticket
-   Ticket Categories
-   Priority Management
-   Open / Pending / Resolved / Closed Tickets
-   Ticket Filters
-   Search and Sorting
-   Internal Notes
-   Reply via Email
-   Ticket History

Suggested lead statuses: New, Assigned, Contacted, Follow-up,
Interested, Converted, Not Interested, Rejected.

# 8. Counselor Management

-   All Counselors
-   Add New Counselor
-   Counselor Profile
-   Edit Counselor
-   Assign Students
-   Student List
-   Student Application Tracking
-   Application Updates
-   Exam Management
-   Student Billing
-   Student Payment History
-   Send Email
-   Counselor Performance
-   Counselor Activity Logs
-   Counselor Status

## 8.1 Counselor Dashboard

Dashboard overview: - Total Assigned Leads - Total Students - New
Students - Active Applications - Pending Documents - Payment Pending -
Payment Confirmed - Visa Processing - Visa Approved - Visa Rejected -
Follow-up Tasks

Dashboard menus: 1. My Dashboard 2. My Leads 3. Assigned Students 4.
Create Student 5. Student File Management 6. Student Documents 7.
Student Profile Update 8. Payment Requests 9. Payment History 10. Due
Payment List 11. Refund Requests 12. Visa Processing 13. Application
Status Update 14. Exam / Work Management 15. Student Communication 16.
Email 17. Live Chat 18. Follow-up Management 19. Reports 20. My Profile

Counselor responsibilities: - Receive leads from the Lead Manager. -
Contact clients and explain the immigration process. - Create student
accounts when necessary. - Open student files. - Generate payment
requests. - Track Accounts payment confirmation. - Update student
documents. - Update visa processing stages. - Notify clients about
important updates. - Request office visits after visa decisions.

Counselors should only access their assigned leads and students unless
additional permissions are granted.

# 9. Account & Finance Management

-   Account Dashboard
-   Income Overview
-   Expense Overview
-   Revenue Reports
-   All Bills
-   Create New Bill
-   Edit / Update Bill
-   Student Fees
-   Payment Records
-   Due Payments
-   Partial Payments
-   Refund Management
-   Invoice Management
-   Payment History
-   Payment Methods
-   Transaction History
-   Financial Reports
-   Monthly / Yearly Reports
-   Export PDF / Excel

## 9.1 Accounts Dashboard

Dashboard overview: - Total Revenue - Total Received Payments - Total
Due Payments - Pending Verification - Total Refunds - Today's
Transactions - Monthly Revenue - Payment Method Statistics

Dashboard menus: 1. Accounts Dashboard 2. All Payments 3. New Payment
Requests 4. Pending Verification 5. Payment Confirmation 6. Rejected
Transactions 7. Due Payments 8. Partial Payments 9. Refund Requests 10.
Refund Processing 11. Student Invoices 12. Payment Receipts 13. Payment
Methods 14. Bank Transactions 15. Gateway Transactions 16. Transaction
History 17. Counselor-wise Collection 18. Student-wise Accounts 19.
Financial Reports 20. Export Reports

Accounts responsibilities: - Receive payment requests from counselors. -
Verify online transactions. - Verify manual bank deposits. - Confirm or
reject payments. - Record transaction references. - Manage refunds. -
Manage partial and due payments. - Generate financial reports.

# 10. Student Management

-   All Students
-   Add New Student
-   Student Profile
-   Edit Student Profile
-   Personal Information
-   Passport Information
-   Educational Information
-   Contact Information
-   Documents Management
-   Application History
-   University Applications
-   Visa Application
-   Payment History
-   Student Fees
-   Outstanding Balance
-   Application Status
-   Assign Counselor
-   Student Notes
-   Send Email
-   Student Activity History

## 10.1 User / Student Dashboard

Dashboard overview: - Personal Information - Student Profile - File
Opening Status - Assigned Counselor Information - Application Progress -
Visa Processing Status - Total Fees - Paid Amount - Due Amount - Refund
Amount - Next Payment - Recent Activities

Dashboard menus: \| Menu \| Features \| \|---\|---\| \| My Profile \|
Edit personal and passport information \| \| My File \| Open file, file
number, documents \| \| My Counselor \| Counselor details and
communication \| \| Documents \| Upload, view and download documents \|
\| Payments \| Pay fees and view invoices \| \| Payment History \| All
transactions and receipts \| \| Due Payments \| Pending payments and
deadlines \| \| Refunds \| Refund requests and status \| \| Visa
Application \| Application details and progress \| \| Application Status
\| Stage-by-stage updates \| \| Notifications \| Payment and visa
notifications \| \| Live Chat \| Contact counselor / support \| \| Email
\| Access assigned email functionality if enabled \|

Example payment history: \| Payment \| Example Amount \| Example Status
\| \|---\|---:\|---\| \| File Opening Fee \| ৳5,000 \| Paid \| \|
Document Processing \| ৳10,000 \| Due \| \| Visa Processing Fee \|
৳20,000 \| Pending \| \| Medical Fee \| ৳8,000 \| Not Generated \|

These amounts and statuses are illustrative examples, not fixed fees or
workflow defaults.

# 11. User & Counselor End-to-End Process

The uploaded process diagram and supplied workflow describe the
following journey.

1.  A visitor submits an inquiry/lead form through the website.
2.  Admin or Lead Manager sorts the lead and assigns it to a counselor.
3.  The counselor contacts the lead and explains the immigration
    process.
4.  The client can register independently, or the counselor can create
    an account on the client's behalf.
5.  The counselor opens a student/client file and records personal,
    educational and passport information.
6.  The system generates the first invoice/payment request for the
    file-opening fee.
7.  The payment request is sent to the Accounts Dashboard for
    verification and confirmation.
8.  Once confirmed, the payment status is synchronized to the User and
    Counselor Dashboards.
9.  The counselor starts visa processing and updates the different
    stages.
10. The visa decision is communicated to the client.
11. Whether approved or rejected, the client is requested to visit the
    office.
12. The office visit is recorded and the case is updated or completed.

## 11.1 Lead Management

-   Website Lead Form
-   All Leads
-   New Leads
-   Pending Leads
-   Lead Sorting
-   Country-Based Filtering
-   Service-Based Filtering
-   Lead Assignment
-   Counselor Assignment
-   Lead Transfer
-   Follow-up Date
-   Contact History
-   Lead Notes
-   Lead Status
-   Convert Lead to Student
-   Duplicate Lead Detection

## 11.2 Student Account & File Opening

-   Client self-registration
-   Counselor-created user account
-   Secure account invitation / password setup
-   Student profile creation
-   Unique student number
-   File opening by counselor
-   Unique file number
-   Personal, educational and passport information
-   Document upload and verification
-   Assigned counselor
-   File-opening fee invoice
-   File status and activity history

# 12. Multi-Step Payment Management System

One student file can have multiple payment steps. Each payment must be
an independent record linked to the student and relevant
application/file.

## 12.1 Payment Workflow

1.  Counselor generates a payment request.
2.  The request includes fee type, amount, invoice and due date.
3.  Student receives the request in the User Dashboard.
4.  Student pays through a supported method or submits manual payment
    evidence.
5.  Payment is sent to Accounts for verification.
6.  Accounts verifies the transaction and evidence.
7.  Accounts confirms or rejects the payment.
8.  The result updates the User, Counselor and Accounts Dashboards.
9.  The system stores the payment and status history.

## 12.2 Payment Types

-   File Opening Fee
-   Registration Fee
-   Application Processing Fee
-   Document Verification Fee
-   University Application Fee
-   Admission Processing Fee
-   Visa Processing Fee
-   Medical Fee
-   Embassy Fee
-   Service Charge
-   Other Custom Fees

Admin should be able to create payment types dynamically.

## 12.3 Payment Methods

-   Bank Transfer
-   Debit / Credit Card
-   Cash Payment
-   Mobile Financial Services
-   Online Payment Gateway
-   Manual Payment Entry

Potential Bangladesh gateway integrations include SSLCommerz, aamarPay
and supported bKash/Nagad payment solutions, subject to merchant
eligibility and provider API support.

## 12.4 Payment Statuses

  Status             Meaning
  ------------------ ------------------------
  Unpaid             Payment not submitted
  Pending            Awaiting verification
  Paid               Confirmed
  Partial            Partially paid
  Due                Outstanding amount
  Failed             Gateway payment failed
  Rejected           Accounts rejected
  Refund Requested   Refund requested
  Refunded           Refund completed

## 12.5 Payment Rules

1.  One student can have unlimited payment records.
2.  One application can have multiple payment steps.
3.  A payment request must generate a unique invoice.
4.  Gateway callbacks must be verified server-side.
5.  Never mark a payment Paid simply because the browser returns to a
    success page.
6.  Accounts confirmation must update all relevant dashboards.
7.  Every status change must be recorded in an audit log.
8.  Refunds must reference the original payment.
9.  Prevent duplicate payment confirmation and duplicate webhook
    processing.
10. Payment records must not be deleted by ordinary users.
11. Support partial payments and outstanding balances.
12. Keep transaction references, method, timestamps and downloadable
    receipts.

# 13. Visa Processing & Application Tracking

The counselor manages visa processing, while the student can see
progress in the User Dashboard.

Suggested application stages: 1. File Opened 2. Initial Documents
Pending 3. Documents Submitted 4. Document Verification 5. University /
Employer Application 6. Application Submitted 7. Application Under
Review 8. Additional Documents Required 9. Interview Scheduled (if
applicable) 10. Visa Application Submitted 11. Visa Processing 12.
Decision Received 13. Visa Approved / Visa Rejected 14. Office Visit
Requested 15. Office Visit Completed 16. Process Completed

Stages should be configurable by Super Admin because different countries
and visa types may have different workflows.

## 13.1 Visa Approved Workflow

-   Upload approval document.
-   Notify student.
-   Request office visit.
-   Record visit completion.

## 13.2 Visa Rejected Workflow

-   Upload rejection document.
-   Record rejection reason.
-   Notify student.
-   Request office visit.
-   Record visit completion.

## 13.3 Status Update History

Every status update should include: - Previous Status - New Status -
Updated By - Update Date - Notes / Remarks - Supporting Documents -
Notification History

The student should see a chronological application timeline.

# 14. Work & Examination Management

-   Work Dashboard
-   Exam Management
-   Create Examination
-   Exam Schedule
-   Exam Categories
-   Student Exam List
-   Assign Exam
-   Exam Results
-   Exam Status
-   Counselor Exam Tracking
-   Exam Reports

# 15. University Management

-   All Universities
-   Add New University
-   Edit University
-   University Profile
-   University Logo
-   University Banner
-   Country Management
-   University Location
-   Available Courses
-   Tuition Fees
-   Admission Requirements
-   Application Deadlines
-   Scholarship Information
-   Intake Management
-   University Gallery
-   University SEO
-   Featured Universities
-   University Status

# 16. Partner Application Management

-   All Partner Applications
-   New Applications
-   Application Details
-   Approve Application
-   Reject Application
-   Pending Applications
-   Partner Profile
-   Partner Documents
-   Partner Verification
-   Partner Status
-   Partner Commission
-   Partner Payment History
-   Partner Activity Logs

# 17. Working Visa Management

-   All Visa Applications
-   Create New Application
-   Visa Categories
-   Country Management
-   Visa Requirements
-   Required Documents
-   Application Tracking
-   Application Status
-   Document Verification
-   Processing Updates
-   Visa Fees
-   Payment Records
-   Visa Reports
-   Visa SEO

# 18. Live Chat Integration -- Tawk.to

Integration reference: https://developer.tawk.to

Use Tawk.to as the live chat engine instead of building a complete chat
infrastructure from scratch.

Requirements: - Website Live Chat Widget - User Dashboard Chat -
Counselor Chat Access - Admin Chat Management - Assign Chat to Counselor
(subject to integration capabilities) - Visitor Name and Email
Synchronization - Student ID / File Number as Custom Attributes - Chat
Status (Online / Offline) - Chat History and Transcripts - Chat
Notifications - Offline Messages - Chat Tags - Department Management

Suggested workflow: Website Visitor → Tawk.to → Counselor / Support →
Student Conversion → Student File.

Technical considerations: - Use the Tawk.to JavaScript API for visitor
identification, widget control and supported events. - Use webhooks for
server-side event notifications where supported. - Use Secure Mode to
authenticate logged-in visitors. - Verify webhook authenticity where
supported and avoid trusting client-submitted identity data. - Confirm
which assignment, history and reporting features are available in the
selected Tawk.to plan.

# 19. Mail Control Integration -- Roundcube

Integration reference: https://roundcube.net/contribute

Use Roundcube as the webmail interface inside the platform.

## 19.1 Email Functionality

-   Inbox
-   Sent
-   Drafts
-   Compose
-   Spam
-   Trash
-   Archive
-   Attachments
-   Email Search
-   Email Folders
-   Address Book
-   Email Signature
-   Multiple Email Accounts (where configured)
-   Email Templates managed by Laravel

## 19.2 Admin Controls

-   Email Account Management
-   Create Email Account
-   Assign Email to User
-   Assign Email to Counselor
-   Email Domain Configuration
-   IMAP Configuration
-   SMTP Configuration
-   Storage / Mailbox Limits
-   Email Access Permissions
-   Email Activity Logs

## 19.3 Technical Requirements

Roundcube is an email client, not an email hosting server. The system
also needs: - Working IMAP server for receiving email. - SMTP server for
sending email. - Mailbox accounts for each user who needs webmail. -
Roundcube installation and configuration. - Secure authentication
integration between Laravel and Roundcube.

Roundcube plugins and configuration can be customized, but a standard
installation does not automatically provide Laravel single sign-on. SSO
requires a separate secure integration strategy. Do not expose mailbox
passwords or IMAP/SMTP credentials to the browser.

# 20. Notification Management

-   All Notifications
-   System Notifications
-   Email Notifications
-   Student Notifications
-   Counselor Notifications
-   Payment Notifications
-   Application Notifications
-   Notification Templates
-   Notification Settings
-   Read / Unread Status
-   In-app Notifications
-   Real-time Notifications where supported

# 21. Website Management -- Dynamic CMS

All website elements should be configurable from the admin panel without
editing code for routine content and appearance changes.

## 21.1 General Website Settings

-   Website Name
-   Website Tagline
-   Website URL
-   Website Description
-   Contact Email
-   Phone Number
-   WhatsApp Number
-   Office Address
-   Business Hours
-   Copyright Text
-   Website Language
-   Default Language
-   Timezone
-   Date & Time Format
-   Currency Settings

## 21.2 Header Management

-   Header Logo
-   Logo Width and Height
-   Header Background Color
-   Header Text Color
-   Header Layout
-   Sticky Header On / Off
-   Top Header Bar
-   Contact Information
-   Social Media Links
-   Header CTA Buttons
-   Login / Register Buttons
-   Mobile Header Settings

## 21.3 Dynamic Menu Management

-   Main Navigation Menu
-   Footer Menu
-   Top Menu
-   Mobile Menu
-   Create / Edit / Delete Menu
-   Drag-and-Drop Menu Sorting
-   Parent / Child Menu
-   Dropdown Menu
-   Custom URL
-   Internal Page Link
-   External Link
-   Open in New Tab
-   Menu Visibility
-   Role-Based Menu Visibility

## 21.4 Footer Management

-   Footer Logo
-   Footer Description
-   Footer Columns
-   Footer Navigation
-   Contact Information
-   Social Media Links
-   Newsletter Subscription
-   Payment Logos
-   Copyright Text
-   Privacy Policy Link
-   Terms & Conditions Link
-   Footer Background and Colors
-   Footer Layout

## 21.5 Logo & Branding

-   Main Website Logo
-   Dark Mode Logo
-   Light Mode Logo
-   Mobile Logo
-   Favicon
-   Apple Touch Icon
-   Login Page Logo
-   Admin Panel Logo
-   Brand Primary Color
-   Secondary Color
-   Accent Color
-   Typography / Font Settings

## 21.6 Theme & Appearance

-   Light Mode
-   Dark Mode
-   System Default Mode
-   Admin Theme Settings
-   Website Theme Settings
-   Primary Color
-   Secondary Color
-   Button Styles
-   Global Typography
-   Heading Fonts
-   Body Fonts
-   Background Settings
-   Custom CSS
-   Custom JavaScript
-   Responsive Layout Settings

## 21.7 Homepage Management

-   Hero Section
-   Banner Slider
-   Banner Images
-   Hero Title and Description
-   CTA Buttons
-   About Section
-   Services Section
-   University Section
-   Popular Countries
-   Featured Courses
-   Why Choose Us
-   Statistics / Counter
-   Testimonials
-   Partner Logos
-   Blog Section
-   FAQ Section
-   Newsletter Section
-   Contact Section
-   Section Visibility On / Off
-   Drag-and-Drop Section Ordering

## 21.8 Website Pages & Sections

-   About Us
-   Contact Us
-   Our Services
-   Study Abroad
-   Working Visa
-   University Listing
-   Student Testimonials
-   FAQ
-   Privacy Policy
-   Terms & Conditions
-   Cookie Policy
-   Custom Landing Pages

Every configurable section should support: - Enable / Disable - Edit
Content - Image Upload - Section Background - Custom Styling - Sorting /
Ordering - SEO Settings

# 22. Complete SEO Management

## 22.1 Global SEO Settings

-   Global SEO Enable / Disable
-   Default Meta Title
-   Default Meta Description
-   Default Meta Keywords
-   Default Canonical URL
-   Default Robots Meta
-   Default Open Graph Image
-   Default Open Graph Title
-   Default Open Graph Description
-   Twitter Card Settings
-   Website Publisher Information
-   Organization Schema
-   Local Business Schema
-   Google Search Console Verification
-   Google Analytics ID
-   Google Tag Manager
-   Bing Webmaster Verification

## 22.2 Individual Page SEO

Every page, blog, university, service and visa page should have
independent SEO controls.

-   SEO Enable / Disable
-   Custom Meta Title
-   Meta Description
-   Meta Keywords
-   Focus Keyword
-   Canonical URL
-   Robots Index / Noindex
-   Follow / Nofollow
-   Open Graph Title
-   Open Graph Description
-   Open Graph Image
-   Twitter Card
-   Schema Markup
-   Custom Structured Data
-   SEO Preview

## 22.3 Technical SEO

-   XML Sitemap Generation
-   Sitemap Enable / Disable
-   Dynamic Sitemap
-   Sitemap URL Management
-   Robots.txt Editor
-   Canonical URL Management
-   301 Redirect Management
-   302 Redirect Management
-   404 Error Page
-   Broken Link Monitoring
-   Breadcrumb Schema
-   Organization Schema
-   Website Schema
-   Image Alt Text Management
-   Pagination SEO
-   Hreflang Management (multilingual)
-   Search Engine Indexing Control

# 23. System & Advanced Settings

## 23.1 System Configuration

-   General Settings
-   Database Configuration Information (read-only, never expose secrets)
-   Cache Management
-   Clear Application Cache
-   Clear Route Cache
-   Clear View Cache
-   Clear Configuration Cache
-   Maintenance Mode
-   Backup Management
-   File Storage Settings
-   Upload Size Limits
-   Error Logs
-   System Logs

## 23.2 Security Settings

-   Two-Factor Authentication
-   Login Attempt Limits
-   Password Policy
-   Session Management
-   IP Restrictions
-   User Access Logs
-   Admin Activity Logs
-   CSRF Protection
-   API Security
-   Backup & Restore
-   Security Notifications

## 23.3 Integration Settings

-   SMTP / Mail Server
-   Google Analytics
-   Google Search Console
-   Google Maps
-   Google reCAPTCHA
-   Facebook Pixel
-   Google Tag Manager
-   Payment Gateway
-   WhatsApp Integration
-   Live Chat Integration
-   Third-Party API
-   Webhook Management

## 23.4 File & Media Management

-   Media Library
-   Upload Images
-   Upload Documents
-   File Categories
-   Image Optimization
-   Image Alt Text
-   File Search
-   File Delete
-   Storage Usage
-   Allowed File Types

## 23.5 Backup & Maintenance

-   Database Backup
-   Website Backup
-   Automated Backup
-   Backup Download
-   Backup Restore
-   Maintenance Page
-   Maintenance Message
-   Scheduled Maintenance

# 24. Reports & Analytics

-   Admin Dashboard Reports
-   Student Registration Reports
-   Counselor Performance Reports
-   University Application Reports
-   Visa Application Reports
-   Partner Application Reports
-   Payment Reports
-   Income & Expense Reports
-   Email Activity Reports
-   Ticket Reports
-   Live Chat Reports
-   Website Traffic Reports
-   Export PDF / Excel / CSV

# 25. Recommended Admin Sidebar Structure

1.  Dashboard
2.  Lead & User Management
3.  Student Management
4.  Counselor Management
5.  Student File Management
6.  Payment & Accounts
7.  Invoice & Refund Management
8.  Visa Processing
9.  University Management
10. Partner Management
11. Working Visa
12. Exam & Work Management
13. Email / Roundcube
14. Live Chat / Tawk.to
15. Ticket & Contact
16. Blog Management
17. Page Management
18. Website CMS
19. SEO Management
20. Reports & Analytics
21. Notifications
22. Media Library
23. Integrations
24. Roles & Permissions
25. System Settings
26. Security & Audit Logs

The sidebar must be dynamic and permission-based. Super Admin can access
all modules; other roles should only see modules they are authorized to
use.

# 26. Database & Technical Architecture

Use one centralized Laravel application and database as the source of
truth for the three dashboards.

Suggested database areas and tables:

  Database area   Suggested tables
  --------------- ---------------------------------------------
  Leads           leads, lead_assignments, lead_followups
  Users           users, roles, permissions
  Students        students, student_files, student_profiles
  Counselors      counselors, counselor_students
  Documents       documents, document_categories
  Payments        payment_requests, payments, payment_types
  Finance         invoices, transactions, refunds
  Applications    visa_applications, application_statuses
  Tracking        application_status_histories
  Communication   notifications, activity_logs
  Chat            chat_integrations, chat_webhook_logs
  Email           email_accounts, email_logs, email_templates
  Website         settings, menus, pages, seo_settings

Architecture rules: - One central student record. - One student can have
multiple files/applications if business rules allow. - Each application
can have multiple payment requests. - Each payment must be connected to
its invoice and student. - Counselor assignments must be tracked. -
Accounts staff control payment confirmation. - All dashboard data must
come from the same source of truth. - Sensitive financial and student
information must be protected by permissions. - Use database
transactions for important financial state changes. - Validate
authorization on the server for every protected action. - Keep audit
history for payment, application, assignment and profile changes.

# 27. Role & Permission Rules

1.  Super Admin can access and control all modules.
2.  Lead Manager can assign and transfer leads.
3.  Counselors can manage only permitted students and files.
4.  Students can access only their own information.
5.  Accounts staff can manage financial records but cannot independently
    change visa decisions.
6.  Counselors cannot confirm their own payment requests.
7.  Payment confirmation must synchronize across all three dashboards.
8.  Every payment must maintain a permanent history.
9.  Every visa update must be recorded with its history.
10. Refunds require authorized approval.
11. All important actions must be recorded in Activity Logs.
12. Notifications should be generated for important payment and
    application events.

# 28. Development Requirements

  Requirement        Implementation
  ------------------ ---------------------------
  Dynamic CMS        Database-driven content
  Admin RBAC         Roles and permissions
  Dynamic Sidebar    Permission-based menus
  Website Settings   Database-driven settings
  Dynamic SEO        Global and page-level
  Theme              Light / Dark
  Notifications      Real-time where supported
  Email              Roundcube integration
  Live Chat          Tawk.to integration
  Media              Centralized media library
  Activity Logs      Admin action tracking
  Forms              Dynamic validation
  Reports            PDF, Excel, CSV
  Responsive UI      Desktop, tablet, mobile
  Security           2FA and access control
  API                REST API integration

# 29. Recommended Development Phases

1.  **Phase 1:** Admin UI, Dashboard, Authentication, Roles &
    Permissions.
2.  **Phase 2:** Leads, Users, Students and Counselors.
3.  **Phase 3:** Student File and Document Management.
4.  **Phase 4:** Three Dashboards and Payment Workflow.
5.  **Phase 5:** Accounts, Invoices, Refunds and Payment Gateways.
6.  **Phase 6:** Visa Processing and Application Tracking.
7.  **Phase 7:** Tawk.to Live Chat Integration.
8.  **Phase 8:** Roundcube Webmail Integration.
9.  **Phase 9:** Blog, Page CMS and Dynamic Website Management.
10. **Phase 10:** Global SEO, Individual SEO and Sitemap Management.
11. **Phase 11:** System Settings, Integrations, Security and Backup.
12. **Phase 12:** Reports, Notifications, Testing and Optimization.

# 30. Final Product Vision

Build this as a **Centralized Immigration Management System (IMS) +
CMS**, rather than just a normal website admin panel.

The three connected dashboards are:

-   **User / Student Dashboard:** Files, documents, payments, invoices,
    application progress, visa status and communication.
-   **Counselor Dashboard:** Leads, student management, file opening,
    documents, payment requests, visa processing and follow-ups.
-   **Accounts Dashboard:** Payment verification, confirmation,
    invoices, due amounts, refunds, transactions and financial reports.

All three dashboards connect to a centralized Laravel database and share
application, financial and communication records according to role-based
permissions.

## Important Workflow Separation

Keep payment management and visa processing as two separate workflows
connected through application rules.

A student might have paid all fees but still be waiting for a visa
decision. Similarly, a visa decision should not automatically mean that
every financial obligation is settled.

**Implementation principle:** Build the shared business logic and
database relationships first, then build the three dashboard interfaces
on top of them. Keep website content, navigation, header, footer,
branding, theme and SEO database-driven so Super Admin can manage
routine website changes without editing Vue components or application
code.

# 31. Frontend Design References & Technology Requirements

## 31.1 Figma Design – Website Frontend

The public-facing RTL Immigration website frontend must follow the user's supplied Figma page designs.

Before implementing frontend pages:
- Inspect the Figma pages, frames, components and available design references supplied by the user.
- Treat the Figma designs as the primary visual source of truth for the website frontend.
- Follow the page layouts, section order, spacing, typography, colors, buttons, cards, navigation, footer, responsive behavior and interaction patterns shown in Figma.
- Reuse shared design patterns and components across pages where appropriate.
- Implement the pages represented in the supplied Figma designs; do not omit sections or replace the designs with a generic template.
- If a required page state, component or responsive layout is not represented in Figma, use the closest consistent design pattern and clearly identify any assumptions.
- Do not invent a new visual direction or redesign the supplied Figma pages unless the user specifically requests it.

## 31.2 Admin Dashboard HTML Sample – Project Folder

A sample admin dashboard HTML file is available in the project folder and must be inspected before designing or implementing the admin interface.

Requirements:
- Locate and inspect the existing dashboard HTML sample in the project folder.
- Use it as the primary reference for the admin dashboard layout, visual style, sidebar, top navigation, cards, tables, forms, buttons, spacing and general UI patterns.
- Recreate the reference in reusable Vue components rather than embedding the static HTML as the final application.
- Preserve the sample's useful layout and design patterns while adapting them to the actual modules and workflows in this requirements document.
- Make the dashboard responsive for desktop, tablet and mobile.
- Keep the public website frontend design and admin dashboard design consistent with their respective references; do not assume they must look identical.
- If the HTML sample cannot be found, report that clearly and ask for its path or file. Do not silently substitute an unrelated dashboard template.

## 31.3 Required Technology Stack

Use the following technology stack unless the existing project structure or a documented technical constraint requires a compatible adjustment:

| Area | Required technology |
|---|---|
| Backend | Laravel |
| Frontend framework | Vue.js 3 |
| Styling | Tailwind CSS |
| Language | JavaScript or TypeScript |
| Database | Use the database configured by the existing Laravel project |
| API / server communication | Laravel routes, controllers and secure validation; use the project's established Inertia or API approach |

TypeScript is recommended for a project of this size because it improves type safety, maintainability and refactoring across the many dashboards and modules. Use Vue 3 with `<script setup lang="ts">` where appropriate if TypeScript is compatible with the existing project. If the current project is JavaScript-based, inspect its structure first and make a deliberate recommendation rather than converting the whole project without approval.

Use the existing Laravel and Vue versions, build tools, routing conventions, authentication system and package choices wherever practical. Do not introduce a second frontend framework or duplicate an existing dependency without a clear technical reason.

## 31.4 Frontend Architecture & Component Standards

- Build the interface with reusable Vue components and layouts.
- Create separate layouts for the public website, Super Admin, Counselor, Accounts and User/Student dashboards where their navigation or access patterns differ.
- Use shared components for tables, filters, search, pagination, forms, modals, confirmation dialogs, status badges, notifications, file uploads and empty states.
- Keep components focused and avoid placing the entire application in a single large Vue file.
- Use consistent naming, folder structure and code formatting.
- Use TypeScript interfaces/types for API responses, form data, permissions and shared domain models when TypeScript is adopted.
- Provide loading, success, validation-error, empty and failure states for interactive screens.
- Add accessible labels, keyboard-friendly controls and clear validation messages.
- Use responsive Tailwind CSS classes and avoid unnecessary custom CSS.
- Do not hardcode editable website content, menus, branding, theme values or SEO metadata in Vue components; load these from the Laravel backend and database-managed settings.
- Do not expose secrets, payment credentials, mail credentials or privileged configuration in frontend code.

## 31.5 Implementation Workflow for the AI Developer

Before changing code:
1. Inspect the existing project folder and identify the Laravel, Vue, Tailwind and JavaScript/TypeScript versions and current architecture.
2. Locate and inspect the user's Figma references and the dashboard sample HTML file in the project folder.
3. Review existing routes, layouts, components, authentication, middleware, models, migrations and permissions before creating replacements.
4. Map each Figma page and HTML dashboard reference to the required frontend pages and reusable components.
5. Identify missing design details or conflicting requirements and ask concise questions only when they block implementation.
6. Prepare an implementation plan that identifies the files and modules to create or update.

During implementation:
- Follow the Figma references for public website pages and the HTML sample for admin dashboard UI.
- Follow the workflows, role boundaries, payment rules and application statuses defined in this document.
- Build and test one module or workflow at a time.
- Reuse existing project functionality instead of duplicating it.
- Keep frontend presentation separate from backend business rules.
- Enforce authorization and validation on the Laravel server; hiding a button in Vue is not sufficient security.
- Use database transactions and appropriate safeguards for payment and other critical operations.

After implementation:
- Run the project's available formatting, linting, type-checking, automated tests and production build commands.
- Fix errors introduced by the changes.
- Check desktop, tablet and mobile layouts.
- Verify role-based access for Super Admin, Lead Manager, Counselor, Accounts and Student/User roles.
- Report completed work, changed files, tests run, known limitations and remaining tasks.

## 31.6 Design & Development Acceptance Criteria

The implementation is acceptable when:
- Public website pages match the supplied Figma references as closely as the available designs allow.
- The admin dashboard follows the supplied HTML sample's design and layout patterns.
- All implemented pages are responsive and usable on desktop, tablet and mobile.
- The application uses Laravel, Vue 3 and Tailwind CSS, with a documented decision on JavaScript versus TypeScript.
- Shared UI elements are reusable and consistent.
- Dynamic website settings and CMS content are controlled through the backend rather than hardcoded frontend values.
- The three operational dashboards share consistent, permission-protected application and payment data.
- Existing project conventions and working functionality are preserved unless a change is necessary and documented.
