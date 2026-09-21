<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Commands;

use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Models\MailboxGrant;
use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Illuminate\Console\Command;

class BackfillMailboxGrantsCommand extends Command
{
    protected $signature = 'intranet-app-abwesenheit:backfill-mailbox-grants
                            {--dry-run : Nur anzeigen, nichts speichern}';

    protected $description = 'Legt MailboxGrant-Einträge für laufende Abwesenheiten mit Postfach-Delegierung nach (Schedules mit Status applied).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-Run: Es werden keine Grants geschrieben.');
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;

        AbwesenheitSchedule::query()
            ->where('status', AbwesenheitScheduleStatus::Applied)
            ->with('user')
            ->orderBy('id')
            ->each(function (AbwesenheitSchedule $schedule) use ($dryRun, &$created, &$skipped, &$failed): void {
                $payload = $schedule->payload ?? [];
                if (! (bool) ($payload['email_delegate'] ?? false)) {
                    $skipped++;

                    return;
                }

                $delegateUsername = (string) ($payload['email_vertreter'] ?? '');
                if ($delegateUsername === '') {
                    $this->error("Schedule #{$schedule->id}: email_vertreter fehlt im Payload.");
                    $failed++;

                    return;
                }

                $user = $schedule->user;
                if (! $user || blank($user->upn)) {
                    $this->error("Schedule #{$schedule->id}: User/UPN fehlt.");
                    $failed++;

                    return;
                }

                $delegate = AbwesenheitModels::user()::firstWhere('username', $delegateUsername);
                if (! $delegate || blank($delegate->upn)) {
                    $this->error("Schedule #{$schedule->id}: Vertreter '{$delegateUsername}' nicht gefunden.");
                    $failed++;

                    return;
                }

                $exists = MailboxGrant::query()
                    ->active()
                    ->where('user_id', $user->getKey())
                    ->where('delegate_upn', $delegate->upn)
                    ->exists();

                if ($exists) {
                    $this->line("Schedule #{$schedule->id}: aktiver Grant existiert bereits — übersprungen.");
                    $skipped++;

                    return;
                }

                $this->info(sprintf(
                    'Schedule #%d: Grant für %s → %s (already_existed=false)',
                    $schedule->id,
                    $user->upn,
                    $delegate->upn,
                ));

                if ($dryRun) {
                    $created++;

                    return;
                }

                MailboxGrant::query()->create([
                    'user_id' => $user->getKey(),
                    'owner_upn' => (string) $user->upn,
                    'delegate_upn' => (string) $delegate->upn,
                    'access_rights' => 'FullAccess',
                    // Laufende Abwesenheit: Recht wurde (vermutlich) durch apply gesetzt → beim Ende Remove.
                    'already_existed' => false,
                    'schedule_id' => $schedule->id,
                    'granted_at' => $schedule->applied_at ?? now(),
                ]);

                $created++;
            });

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['created'.($dryRun ? ' (dry-run)' : ''), $created],
                ['skipped', $skipped],
                ['failed', $failed],
            ]
        );

        $this->comment('Hinweis: Sofort-Abwesenheiten ohne Schedule werden nicht erfasst. Pending-Schedules brauchen keinen Backfill (Grant entsteht beim Apply).');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
