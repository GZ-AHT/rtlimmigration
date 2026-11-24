<?php

namespace App\Support\Notifications;

use App\Notifications\ApplicationUpdated;
use App\Notifications\ChatActivity;
use App\Notifications\ChatMessageReceived;
use App\Notifications\ChatSecurityAlert;
use App\Notifications\ConsultationBooked;
use App\Notifications\ConsultationCancelled;
use App\Notifications\ConsultationRescheduleRequested;
use App\Notifications\DocumentReviewed;
use App\Notifications\DocumentUploaded;
use App\Notifications\FileOpeningFeeNeeded;
use App\Notifications\FollowUpDue;
use App\Notifications\LeadAssigned;
use App\Notifications\NewLeadReceived;
use App\Notifications\PartnerApplicationReceived;
use App\Notifications\PaymentDecided;
use App\Notifications\PaymentRequested;
use App\Notifications\PaymentSubmitted;
use App\Notifications\RefundUpdated;
use App\Notifications\SecurityAlert;
use App\Notifications\StudentAssigned;
use App\Notifications\StudentFileOpenedNotification;
use App\Notifications\StudentRegistered;
use App\Notifications\TicketAssigned;

/**
 * Every in-app notification the system can send (REQ §20), with the name an
 * administrator sees on the Notification templates screen. Security alerts
 * are mandatory: no preference or template can switch their in-app copy off.
 * A test keeps this list in step with app/Notifications.
 */
class NotificationCatalog
{
    /** category => label, in the order REQ §20 lists them. */
    public const CATEGORIES = [
        'system' => 'System',
        'email' => 'Email',
        'student' => 'Student',
        'counselor' => 'Counselor',
        'payment' => 'Payment',
        'application' => 'Application',
    ];

    /** @var array<class-string, array{label: string, category: string, mandatory: bool}> */
    public const TYPES = [
        NewLeadReceived::class => ['label' => 'New website enquiry', 'category' => 'system', 'mandatory' => false],
        ConsultationBooked::class => ['label' => 'New consultation booking', 'category' => 'system', 'mandatory' => false],
        ConsultationCancelled::class => ['label' => 'Consultation cancelled by the customer', 'category' => 'system', 'mandatory' => false],
        ConsultationRescheduleRequested::class => ['label' => 'Consultation reschedule requested', 'category' => 'system', 'mandatory' => false],
        PartnerApplicationReceived::class => ['label' => 'New partner application', 'category' => 'system', 'mandatory' => false],
        SecurityAlert::class => ['label' => 'Security alert', 'category' => 'system', 'mandatory' => true],
        ChatSecurityAlert::class => ['label' => 'Chat security alert', 'category' => 'system', 'mandatory' => true],
        TicketAssigned::class => ['label' => 'Ticket assigned', 'category' => 'system', 'mandatory' => false],
        StudentRegistered::class => ['label' => 'New student registration', 'category' => 'student', 'mandatory' => false],
        StudentAssigned::class => ['label' => 'Student assigned to a counselor', 'category' => 'student', 'mandatory' => false],
        DocumentUploaded::class => ['label' => 'Document uploaded', 'category' => 'student', 'mandatory' => false],
        DocumentReviewed::class => ['label' => 'Document verified or rejected', 'category' => 'student', 'mandatory' => false],
        ChatMessageReceived::class => ['label' => 'New chat message', 'category' => 'student', 'mandatory' => false],
        ChatActivity::class => ['label' => 'Chat activity', 'category' => 'student', 'mandatory' => false],
        LeadAssigned::class => ['label' => 'Lead assigned', 'category' => 'counselor', 'mandatory' => false],
        FollowUpDue::class => ['label' => 'Lead follow-up due', 'category' => 'counselor', 'mandatory' => false],
        PaymentRequested::class => ['label' => 'Payment requested', 'category' => 'payment', 'mandatory' => false],
        PaymentSubmitted::class => ['label' => 'Payment submitted', 'category' => 'payment', 'mandatory' => false],
        PaymentDecided::class => ['label' => 'Payment confirmed or rejected', 'category' => 'payment', 'mandatory' => false],
        RefundUpdated::class => ['label' => 'Refund update', 'category' => 'payment', 'mandatory' => false],
        FileOpeningFeeNeeded::class => ['label' => 'File opening fee needed', 'category' => 'payment', 'mandatory' => false],
        ApplicationUpdated::class => ['label' => 'Application stage update', 'category' => 'application', 'mandatory' => false],
        StudentFileOpenedNotification::class => ['label' => 'Student file opened', 'category' => 'application', 'mandatory' => false],
    ];

    /** Placeholders an email template may use. */
    public const PLACEHOLDERS = ['{name}', '{title}', '{body}', '{url}', '{app}'];
}
