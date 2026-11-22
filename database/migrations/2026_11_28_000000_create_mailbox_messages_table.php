<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copies of mail received in the cPanel mailboxes (via IMAP) so staff can
     * read it in the dashboard. The mailbox stays the source of truth; one row
     * per message per mailbox, so a repeated sync never imports it twice.
     */
    public function up(): void
    {
        Schema::create('mailbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->string('folder', 12); // inbox | drafts | spam | trash
            $table->string('remote_folder'); // the folder's real name on the mail server
            $table->unsignedBigInteger('uid');
            $table->string('message_id', 255)->nullable();
            // Same message, same mailbox = same key (Message-ID, or a hash of its headers when it has none).
            $table->char('dedupe_key', 40);
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('subject', 500)->nullable();
            $table->string('preview', 255)->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable(); // already sanitised
            $table->json('attachments')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->boolean('is_read')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['email_account_id', 'dedupe_key']);
            $table->index(['email_account_id', 'folder', 'received_at']);
            $table->index(['email_account_id', 'remote_folder', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_messages');
    }
};
