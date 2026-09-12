<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_sends', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('body_html');
            $table->string('audience_type');
            $table->json('audience_value')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->foreignId('sent_by')->constrained('users');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_send_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('notification_send_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notification_sends');
    }
};
