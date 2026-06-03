<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'allow_supervisor_mailbox_delegation')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('allow_supervisor_mailbox_delegation')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'allow_supervisor_mailbox_delegation')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('allow_supervisor_mailbox_delegation');
        });
    }
};
