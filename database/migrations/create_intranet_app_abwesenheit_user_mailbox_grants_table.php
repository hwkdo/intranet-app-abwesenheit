<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_abwesenheit_mailbox_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('owner_upn');
            $table->string('delegate_upn');
            $table->string('access_rights', 64)->default('FullAccess');
            $table->boolean('already_existed')->default(false);
            $table->foreignId('schedule_id')
                ->nullable()
                ->constrained('intranet_app_abwesenheit_schedules')
                ->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at'], 'iaa_mb_grants_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_abwesenheit_mailbox_grants');
    }
};
