<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitApplyResult;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-abwesenheit', 'web');
});

/**
 * @param  array{outlook?: mixed, phone?: string, d3?: array{abwesend?: bool, vertreter?: mixed}, mailbox?: array{delegated?: bool, delegates?: list<array{upn: string, name: string}>}, fetch_errors?: array<string, bool>}  $status
 */
function mockMeineAbwesenheitShow(array $status = []): AbwesenheitService
{
    $service = mock(AbwesenheitService::class)->makePartial();
    $service->shouldReceive('show')->andReturn([
        'outlook' => $status['outlook'] ?? ['status' => 'disabled'],
        'phone' => $status['phone'] ?? '',
        'd3' => $status['d3'] ?? ['abwesend' => false, 'vertreter' => null],
        'mailbox' => $status['mailbox'] ?? ['delegated' => false, 'delegates' => []],
        'fetch_errors' => $status['fetch_errors'] ?? [],
    ]);

    return $service;
}

test('meine abwesenheit zeigt postfach-delegierung als statuszeile', function (): void {
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-abwesenheit');

    mockMeineAbwesenheitShow([
        'mailbox' => [
            'delegated' => true,
            'delegates' => [
                ['upn' => 'vertreter@example.test', 'name' => 'Anna Vertreter'],
            ],
        ],
    ]);

    Volt::test('apps.abwesenheit.meine')
        ->actingAs($user)
        ->assertSee('Postfach delegiert an Anna Vertreter')
        ->assertDontSee('Keine Postfach-Delegierung eingerichtet');
});

test('erweiterter modus verlangt telefon-vertreter nur bei aktiver umleitung', function (): void {
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-abwesenheit');

    $service = mockMeineAbwesenheitShow();
    $service->shouldReceive('apply')
        ->once()
        ->andReturn(new AbwesenheitApplyResult(warnings: []));
    $service->shouldReceive('resolveCallDestination')->never();

    Volt::test('apps.abwesenheit.meine')
        ->actingAs($user)
        ->set('extendedMode', true)
        ->set('call_forwarding', false)
        ->set('d3_forwarding', false)
        ->set('email_vertreter', 'email.vertreter')
        ->set('phone_vertreter', '')
        ->set('d3_vertreter', '')
        ->call('save')
        ->assertHasNoErrors(['phone_vertreter', 'd3_vertreter']);
});

test('erweiterter modus verlangt telefon-vertreter wenn umleitung aktiv ist', function (): void {
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-abwesenheit');

    mockMeineAbwesenheitShow();

    Volt::test('apps.abwesenheit.meine')
        ->actingAs($user)
        ->set('extendedMode', true)
        ->set('call_forwarding', true)
        ->set('d3_forwarding', false)
        ->set('email_vertreter', 'email.vertreter')
        ->set('phone_vertreter', '')
        ->set('d3_vertreter', '')
        ->call('save')
        ->assertHasErrors(['phone_vertreter'])
        ->assertHasNoErrors(['d3_vertreter']);
});

test('erweiterter modus verlangt d3-vertreter nur bei aktiver d3-umleitung', function (): void {
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-abwesenheit');

    mockMeineAbwesenheitShow();

    Volt::test('apps.abwesenheit.meine')
        ->actingAs($user)
        ->set('extendedMode', true)
        ->set('call_forwarding', false)
        ->set('d3_forwarding', true)
        ->set('email_vertreter', 'email.vertreter')
        ->set('phone_vertreter', '')
        ->set('d3_vertreter', '')
        ->call('save')
        ->assertHasErrors(['d3_vertreter'])
        ->assertHasNoErrors(['phone_vertreter']);
});
