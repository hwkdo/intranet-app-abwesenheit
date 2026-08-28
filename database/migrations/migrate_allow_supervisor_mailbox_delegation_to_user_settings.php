<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Data\UserSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'allow_supervisor_mailbox_delegation')) {
            return;
        }

        User::query()
            ->where('allow_supervisor_mailbox_delegation', true)
            ->orderBy('id')
            ->each(function (User $user): void {
                $user->settings = $user->settings->updateAppSettings('abwesenheit', [
                    'allowSupervisorMailboxDelegation' => true,
                ]);
                $user->save();
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('allow_supervisor_mailbox_delegation');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'allow_supervisor_mailbox_delegation')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('allow_supervisor_mailbox_delegation')->default(false);
        });

        User::query()
            ->orderBy('id')
            ->each(function (User $user): void {
                $settings = data_get($user->settings, 'app.abwesenheit');

                if (! $settings instanceof UserSettings) {
                    return;
                }

                if (! $settings->allowSupervisorMailboxDelegation) {
                    return;
                }

                $user->allow_supervisor_mailbox_delegation = true;
                $user->save();
            });
    }
};
