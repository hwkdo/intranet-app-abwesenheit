<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Commands;

use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Models\MailboxGrant;
use Hwkdo\MsGraphLaravel\Models\OutOfOfficeStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class DiagnoseImmediateAbsencesCommand extends Command
{
    protected $signature = 'intranet-app-abwesenheit:diagnose-immediate-absences
                            {--with-exchange : FullAccess-Rechte aus Exchange je Kandidat anzeigen}';

    protected $description = 'Listet aus dem OOO-Cache aktive Sofort-Abwesenheiten ohne applied Schedule und ohne aktiven MailboxGrant (nur Diagnose).';

    public function handle(): int
    {
        $withExchange = (bool) $this->option('with-exchange');

        $appliedUserIds = AbwesenheitSchedule::query()
            ->where('status', AbwesenheitScheduleStatus::Applied)
            ->pluck('user_id');

        $grantUserIds = MailboxGrant::query()
            ->active()
            ->pluck('user_id');

        $excludeIds = $appliedUserIds->merge($grantUserIds)->unique()->values();

        $now = now();

        $statuses = OutOfOfficeStatus::query()
            ->with('user')
            ->where(function ($query) use ($now): void {
                $query->where('status', 'alwaysEnabled')
                    ->orWhere(function ($scheduled) use ($now): void {
                        $scheduled->where('status', 'scheduled')
                            ->whereNotNull('scheduled_start_at')
                            ->whereNotNull('scheduled_end_at')
                            ->where('scheduled_start_at', '<=', $now)
                            ->where('scheduled_end_at', '>=', $now);
                    });
            })
            ->when($excludeIds->isNotEmpty(), fn ($query) => $query->whereNotIn('user_id', $excludeIds))
            ->orderBy('user_id')
            ->get();

        $candidates = $statuses
            ->filter(function (OutOfOfficeStatus $status): bool {
                $user = $status->user;

                return $user !== null && (bool) ($user->active ?? false);
            })
            ->map(function (OutOfOfficeStatus $status) use ($withExchange): array {
                $user = $status->user;

                return [
                    'id' => $user->getKey(),
                    'username' => (string) $user->username,
                    'upn' => (string) ($user->upn ?? ''),
                    'ooo_status' => (string) $status->status,
                    'synced_at' => $status->synced_at?->toDateTimeString() ?? '—',
                    'exchange_full_access' => $withExchange
                        ? $this->exchangeFullAccessUsers((string) ($user->upn ?? ''))
                        : '—',
                ];
            })
            ->values();

        if ($candidates->isEmpty()) {
            $this->info('Keine Kandidaten gefunden (OOO-Cache).');

            return self::SUCCESS;
        }

        $this->table(
            ['User-ID', 'Username', 'UPN', 'OOO-Status', 'Synced at', 'Exchange FullAccess (ohne SELF)'],
            $candidates->map(fn (array $row): array => [
                $row['id'],
                $row['username'],
                $row['upn'],
                $row['ooo_status'],
                $row['synced_at'],
                $row['exchange_full_access'],
            ])->all()
        );

        $this->newLine();
        $this->info('Kandidaten: '.$candidates->count());
        $this->comment('Quelle: ms_graph_laravel_out_of_office_stati (kein Live-Graph). Ob Postfach-Delegierung gesetzt war, bleibt unklar — keine Grants werden geschrieben.');

        return self::SUCCESS;
    }

    private function exchangeFullAccessUsers(string $ownerUpn): string
    {
        if ($ownerUpn === '') {
            return '(kein UPN)';
        }

        try {
            $permissions = app(HwkAdminService::class)->getExchangePermission($ownerUpn);
            if (! $permissions instanceof Collection) {
                return '(Abruf fehlgeschlagen)';
            }

            $delegates = $permissions
                ->filter(function (mixed $permission): bool {
                    if (! is_object($permission) || ! isset($permission->User, $permission->AccessRights)) {
                        return false;
                    }

                    if (strcasecmp((string) $permission->User, 'NT AUTHORITY\\SELF') === 0) {
                        return false;
                    }

                    return collect($permission->AccessRights)->contains(
                        fn (mixed $right): bool => strcasecmp((string) $right, 'FullAccess') === 0
                    );
                })
                ->map(fn (mixed $permission): string => (string) $permission->User)
                ->values()
                ->all();

            return $delegates === [] ? '(keine)' : implode(', ', $delegates);
        } catch (Throwable $exception) {
            report($exception);

            return '(Fehler)';
        }
    }
}
