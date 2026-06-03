<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitApplyResult;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\mock;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('api-manage-out-of-office');

    $this->actor = User::factory()->create([
        'username' => 'actor.user',
        'active' => true,
    ]);
    $this->actor->givePermissionTo('api-manage-out-of-office');

    $this->target = User::factory()->create([
        'username' => 'target.user',
        'active' => true,
    ]);

    Passport::actingAs($this->actor);
});

test('legacy store payload delegates to abwesenheit service and returns expected json', function (): void {
    $service = mock(AbwesenheitService::class);
    $service->shouldReceive('apply')
        ->once()
        ->with(
            Mockery::on(fn (User $user): bool => $user->is($this->target)),
            Mockery::on(function ($data): bool {
                return $data->email_vertreter === 'vertreter.user'
                    && $data->email_delegate === true
                    && $data->call_forwarding === true
                    && $data->d3_forwarding === true
                    && $data->call_destination === '1234';
            })
        )
        ->andReturn(new AbwesenheitApplyResult(warnings: ['Test-Warnung']));

    $this->app->instance(AbwesenheitService::class, $service);

    postJson('/api/abwesenheit/'.$this->target->username, [
        'email_vertreter' => 'vertreter.user',
        'email_delegate' => true,
        'call_forwarding' => true,
        'd3_forwarding' => true,
        'call_destination' => '1234',
        'notice' => 'Hinweis',
        'start' => null,
        'end' => null,
    ])
        ->assertOk()
        ->assertJson([
            'message' => 'Abwesenheit set',
            'warnings' => ['Test-Warnung'],
        ]);
});

test('show returns outlook phone and d3 from service', function (): void {
    $service = mock(AbwesenheitService::class);
    $service->shouldReceive('show')
        ->once()
        ->andReturn([
            'outlook' => ['status' => 'disabled'],
            'phone' => '',
            'd3' => ['abwesend' => false],
            'fetch_errors' => [],
        ]);

    $this->app->instance(AbwesenheitService::class, $service);

    getJson('/api/abwesenheit/'.$this->target->username)
        ->assertOk()
        ->assertJsonStructure(['outlook', 'phone', 'd3']);
});

test('destroy delegates to service and returns message', function (): void {
    $service = mock(AbwesenheitService::class);
    $service->shouldReceive('destroy')->once();

    $this->app->instance(AbwesenheitService::class, $service);

    deleteJson('/api/abwesenheit/'.$this->target->username)
        ->assertOk()
        ->assertJson(['message' => 'Abwesenheit removed']);
});
