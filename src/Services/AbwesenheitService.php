<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Services;

use Hwkdo\CiscoPhoneServicesLaravel\Interfaces\AxlServiceInterface;
use Hwkdo\D3RestLaravel\models\BenutzerAbwesenheit;
use Hwkdo\HwkAdminLaravel\DTO\SetExchangePermissionDTO;
use Hwkdo\HwkAdminLaravel\HwkAdminService;
use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitApplyResult;
use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitStoreData;
use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailboxServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOutOfOfficeTemplateServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class AbwesenheitService
{
    public function apply(Model $user, AbwesenheitStoreData $data): AbwesenheitApplyResult
    {
        if ($data->email_delegate) {
            defer(function () use ($user, $data): void {
                $delegate = AbwesenheitModels::user()::firstWhere('username', $data->email_vertreter);
                if (! $delegate) {
                    return;
                }

                app(HwkAdminService::class)->setExchangePermission(new SetExchangePermissionDTO(
                    owner_upn: $user->upn,
                    delegate_upn: $delegate->upn,
                    accessRights: 'FullAccess',
                    action: 'Add'
                ));
            });
        }

        if ($data->call_forwarding) {
            $destination = $data->call_destination ?? $this->resolveCallDestination($data->phoneVertreterUsername());
            if ($destination !== null && $destination !== '') {
                app(AxlServiceInterface::class)->setLineForwardAllDestination($user->linepattern, $destination);
            }
        }

        $emailColleague = AbwesenheitModels::user()::firstWhere('username', $data->email_vertreter);
        $message = app(MsGraphOutOfOfficeTemplateServiceInterface::class)->getTemplate(
            user: $user,
            colleague: $emailColleague,
            limit: $data->end,
            notice: $data->notice
        );

        app(MsGraphMailboxServiceInterface::class)->setOutOfOffice(
            upn: $user->upn,
            message: $message,
            von: $data->start,
            bis: $data->end
        );

        $warnings = [];

        if ($data->d3_forwarding) {
            $vertretungUsername = str($data->d3VertreterUsername())->beforeLast('@')->value();

            try {
                $d3Response = $this->d3Api()->setUserAbsence(
                    username: $user->username,
                    vertretung_username: $vertretungUsername,
                    text: null,
                    start_date: $data->start?->toIso8601String() ?? now()->toIso8601String(),
                    end_date: $data->end?->toIso8601String(),
                    raw: true
                );

                $isUnexpectedD3Response = ! is_array($d3Response)
                    || ! array_key_exists('userId', $d3Response)
                    || ! array_key_exists('isAbsent', $d3Response);

                if ($isUnexpectedD3Response) {
                    $warnings[] = 'Abwesenheit wurde gesetzt, aber Vertretung in d3 ist in diesem Zeitraum nicht moeglich.';
                }
            } catch (Throwable $exception) {
                report($exception);
                $warnings[] = 'Abwesenheit wurde gesetzt, aber Vertretung in d3 ist in diesem Zeitraum nicht moeglich.';
            }
        }

        return new AbwesenheitApplyResult(warnings: $warnings);
    }

    /**
     * @return array{
     *     outlook: mixed,
     *     phone: string,
     *     d3: array{abwesend: bool, vertreter: array<string, string>|null},
     *     fetch_errors: array{outlook?: bool, phone?: bool, d3?: bool}
     * }
     */
    public function show(Model $user): array
    {
        $fetchErrors = [];

        $outlook = $this->fetchOutlookStatus($user, $fetchErrors);
        $phone = $this->fetchPhoneStatus($user, $fetchErrors);
        $d3 = $this->fetchD3Status($user, $fetchErrors);

        return [
            'outlook' => $outlook,
            'phone' => $phone,
            'd3' => $d3,
            'fetch_errors' => $fetchErrors,
        ];
    }

    public function isActive(Model $user): bool
    {
        $status = $this->show($user);

        if ($this->isOutlookActive($status['outlook'])) {
            return true;
        }

        if (strlen((string) ($status['phone'] ?? '')) > 0) {
            return true;
        }

        if (($status['d3']['abwesend'] ?? false) === true) {
            return true;
        }

        return false;
    }

    public function destroy(Model $user): void
    {
        $status = $this->show($user);

        if ($this->isOutlookActive($status['outlook'])) {
            try {
                app(MsGraphMailboxServiceInterface::class)->removeOutOfOffice($user->upn);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (strlen((string) ($status['phone'] ?? '')) > 0) {
            try {
                app(AxlServiceInterface::class)->setLineForwardAllDestination($user->linepattern, '');
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (($status['d3']['abwesend'] ?? false) === true) {
            try {
                $this->d3Api()->unsetUserAbsence(
                    username: $user->username,
                    raw: false
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        defer(function () use ($user): void {
            try {
                app(HwkAdminService::class)->resetExchangePermission($user->upn);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function resolveCallDestination(string $vertreterUsername): ?string
    {
        $vertreter = AbwesenheitModels::user()::firstWhere('username', $vertreterUsername);
        if (! $vertreter || ! $vertreter->telefon) {
            return null;
        }

        return str($vertreter->telefon)->afterLast('-')->value();
    }

    private function isOutlookActive(mixed $outlook): bool
    {
        if (is_array($outlook)) {
            $status = $outlook['status'] ?? null;

            if ($status === 'unavailable') {
                return false;
            }

            return in_array($status, ['alwaysEnabled', 'scheduled'], true);
        }

        if (is_object($outlook) && method_exists($outlook, 'getStatus')) {
            $status = $outlook->getStatus();
            if (method_exists($status, 'value')) {
                return in_array($status->value(), ['alwaysEnabled', 'scheduled'], true);
            }
        }

        return false;
    }

    /**
     * @param  array<string, bool>  $fetchErrors
     */
    private function fetchOutlookStatus(Model $user, array &$fetchErrors): mixed
    {
        try {
            return app(MsGraphMailboxServiceInterface::class)->getAutoReplySettings($user->upn, false);
        } catch (Throwable $exception) {
            report($exception);
            $fetchErrors['outlook'] = true;

            return ['status' => 'unavailable'];
        }
    }

    /**
     * @param  array<string, bool>  $fetchErrors
     */
    private function fetchPhoneStatus(Model $user, array &$fetchErrors): string
    {
        $linepattern = trim((string) ($user->linepattern ?? ''));
        if ($linepattern === '') {
            return '';
        }

        try {
            return app(AxlServiceInterface::class)->getLineForwardAllDestination($linepattern);
        } catch (Throwable $exception) {
            report($exception);
            $fetchErrors['phone'] = true;

            return '';
        }
    }

    /**
     * @param  array<string, bool>  $fetchErrors
     * @return array{abwesend: bool, vertreter: array<string, string>|null}
     */
    private function fetchD3Status(Model $user, array &$fetchErrors): array
    {
        try {
            $userId = $this->d3Api()->getUserIdByUsername($user->username);
            $d3Raw = $this->d3Api()->getUserAbsence(
                user_id: $userId,
                raw: false
            );

            return $this->normalizeD3Status($d3Raw);
        } catch (Throwable $exception) {
            report($exception);
            $fetchErrors['d3'] = true;

            return [
                'abwesend' => false,
                'vertreter' => null,
            ];
        }
    }

    /**
     * @return array{abwesend: bool, vertreter: array<string, string>|null}
     */
    private function normalizeD3Status(mixed $d3): array
    {
        if (is_array($d3)) {
            return [
                'abwesend' => (bool) ($d3['abwesend'] ?? $d3['isAbsent'] ?? false),
                'vertreter' => $this->normalizeD3Vertreter($d3['vertreter'] ?? null),
            ];
        }

        if ($d3 instanceof BenutzerAbwesenheit) {
            return [
                'abwesend' => $d3->abwesend,
                'vertreter' => $this->normalizeD3Vertreter($d3->vertreter),
            ];
        }

        return [
            'abwesend' => false,
            'vertreter' => null,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function normalizeD3Vertreter(mixed $vertreter): ?array
    {
        if ($vertreter === null) {
            return null;
        }

        if (is_array($vertreter)) {
            return [
                'vorname' => (string) ($vertreter['vorname'] ?? ''),
                'nachname' => (string) ($vertreter['nachname'] ?? ''),
                'name' => (string) ($vertreter['name'] ?? ''),
            ];
        }

        if ($vertreter instanceof Authenticatable) {
            return [
                'vorname' => (string) ($vertreter->vorname ?? ''),
                'nachname' => (string) ($vertreter->nachname ?? ''),
                'name' => (string) ($vertreter->name ?? ''),
            ];
        }

        return null;
    }

    private function d3Api(): object
    {
        return app(config('intranet-app-abwesenheit.d3_api'));
    }
}
