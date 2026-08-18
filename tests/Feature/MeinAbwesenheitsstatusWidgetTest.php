<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Dashboard\AbwesenheitDashboardWidgetProvider;
use Hwkdo\IntranetAppAbwesenheit\Enums\AbwesenheitScheduleStatus;
use Hwkdo\IntranetAppAbwesenheit\IntranetAppAbwesenheit;
use Hwkdo\IntranetAppAbwesenheit\Models\AbwesenheitSchedule;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Hwkdo\IntranetAppBase\Services\DashboardWidgetRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('see-app-abwesenheit', 'web');
});

function abwesenheitStatusWidgetUser(): User
{
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-abwesenheit');

    return $user;
}

/**
 * @param  array{outlook?: mixed, phone?: string, d3?: array{abwesend?: bool, vertreter?: mixed}, fetch_errors?: array<string, bool>}  $status
 */
function mockAbwesenheitShow(array $status): AbwesenheitService
{
    $service = mock(AbwesenheitService::class)->makePartial();
    $service->shouldReceive('show')->andReturn([
        'outlook' => $status['outlook'] ?? ['status' => 'disabled'],
        'phone' => $status['phone'] ?? '',
        'd3' => $status['d3'] ?? ['abwesend' => false, 'vertreter' => null],
        'fetch_errors' => $status['fetch_errors'] ?? [],
    ]);

    return $service;
}

test('abwesenheit stellt das status-widget bereit', function (): void {
    expect(IntranetAppAbwesenheit::dashboardWidgetProviders())->toContain(AbwesenheitDashboardWidgetProvider::class);

    $widget = collect(AbwesenheitDashboardWidgetProvider::widgets())
        ->firstWhere('key', AbwesenheitDashboardWidgetProvider::KEY_MEIN_ABWESENHEITSSTATUS);

    expect($widget)->not->toBeNull()
        ->and($widget->title)->toBe('Mein Abwesenheitsstatus')
        ->and($widget->component)->toBe('intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus')
        ->and($widget->supportsItemCount)->toBeFalse()
        ->and($widget->defaultEnabled)->toBeTrue();
});

test('main dashboard registriert das abwesenheit widget für berechtigte nutzer', function (): void {
    $user = abwesenheitStatusWidgetUser();

    $keys = collect(app(DashboardWidgetRegistry::class)->widgetsForMainDashboard($user))
        ->map(fn ($definition): string => $definition->key)
        ->all();

    expect($keys)->toContain('abwesenheit.mein-abwesenheitsstatus');
});

test('widget zeigt anwesend ohne einrichtungsformular', function (): void {
    $user = abwesenheitStatusWidgetUser();
    mockAbwesenheitShow([]);

    Livewire::actingAs($user)
        ->test('intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus')
        ->assertSee('Mein Abwesenheitsstatus')
        ->assertSee('Anwesend')
        ->assertSee('Outlook')
        ->assertSee('Telefon')
        ->assertSee('d3')
        ->assertSee('Zur App')
        ->assertDontSee('Wieder anwesend')
        ->assertDontSee('Jetzt aktivieren')
        ->assertDontSee('Abwesenheit einrichten')
        ->assertDontSee('Vertretung');
});

test('widget zeigt kanalstatus und vertretung bei aktiver abwesenheit', function (): void {
    $user = abwesenheitStatusWidgetUser();
    mockAbwesenheitShow([
        'outlook' => [
            'status' => 'scheduled',
            'scheduledEndDateTime' => ['dateTime' => '2026-08-20T22:00:00'],
        ],
        'phone' => '429',
        'd3' => [
            'abwesend' => true,
            'vertreter' => [
                'vorname' => 'Anna',
                'nachname' => 'Vertretung',
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test('intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus')
        ->assertSee('Abwesend')
        ->assertSee('Bis 20.08.2026')
        ->assertSee('Umgeleitet auf 429')
        ->assertSee('Vertretung: Anna Vertretung')
        ->assertSee('Wieder anwesend')
        ->assertDontSee('Abwesenheit einrichten')
        ->assertDontSee('Jetzt aktivieren');
});

test('widget deaktiviert abwesenheit per klick', function (): void {
    $user = abwesenheitStatusWidgetUser();
    $creator = User::factory()->create(['active' => true]);

    AbwesenheitSchedule::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addWeek(),
        'payload' => [],
        'status' => AbwesenheitScheduleStatus::Applied,
    ]);

    $service = mock(AbwesenheitService::class)->makePartial();
    $service->shouldReceive('show')->andReturn(
        [
            'outlook' => ['status' => 'alwaysEnabled'],
            'phone' => '',
            'd3' => ['abwesend' => false, 'vertreter' => null],
            'fetch_errors' => [],
        ],
        [
            'outlook' => ['status' => 'disabled'],
            'phone' => '',
            'd3' => ['abwesend' => false, 'vertreter' => null],
            'fetch_errors' => [],
        ],
    );
    $service->shouldReceive('destroy')->once();

    Livewire::actingAs($user)
        ->test('intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus')
        ->assertSee('Wieder anwesend')
        ->call('deactivate')
        ->assertDontSee('Wieder anwesend')
        ->assertSee('Anwesend');

    expect(AbwesenheitSchedule::query()->where('user_id', $user->id)->first()->status)
        ->toBe(AbwesenheitScheduleStatus::EndedEarly);
});

test('widget zeigt geplante abwesenheit und storniert per klick', function (): void {
    $user = abwesenheitStatusWidgetUser();
    mockAbwesenheitShow([]);

    $schedule = AbwesenheitSchedule::query()->create([
        'user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'starts_at' => now()->addDay()->startOfDay(),
        'ends_at' => now()->addWeek()->endOfDay(),
        'payload' => [],
        'status' => AbwesenheitScheduleStatus::Pending,
    ]);

    Livewire::actingAs($user)
        ->test('intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus')
        ->assertSee('Geplant')
        ->assertSee($schedule->starts_at->format('d.m.Y'))
        ->assertSee($schedule->ends_at->format('d.m.Y'))
        ->assertSee('Stornieren')
        ->assertDontSee('Wieder anwesend')
        ->call('cancelSchedule', $schedule->id)
        ->assertDontSee('Stornieren');

    expect($schedule->fresh()->status)->toBe(AbwesenheitScheduleStatus::Cancelled);
});

test('widget zeigt abruffehler als hinweise', function (): void {
    $user = abwesenheitStatusWidgetUser();
    mockAbwesenheitShow([
        'outlook' => ['status' => 'unavailable'],
        'fetch_errors' => [
            'outlook' => true,
            'phone' => true,
            'd3' => true,
        ],
    ]);

    Livewire::actingAs($user)
        ->test('intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus')
        ->assertSee('Outlook-Status konnte nicht abgerufen werden.')
        ->assertSee('Telefonstatus konnte nicht abgerufen werden.')
        ->assertSee('d3-Status konnte nicht abgerufen werden.')
        ->assertSee('Anwesend')
        ->assertDontSee('Wieder anwesend');
});

test('widget ist ohne app-berechtigung nicht sichtbar', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus')
        ->assertForbidden();
});
