<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Interfaces\D3ApiInterface;
use Hwkdo\IntranetAppAbwesenheit\Data\AbwesenheitStoreData;
use Hwkdo\IntranetAppAbwesenheit\Models\MailboxGrant;
use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitService;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailboxServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOutOfOfficeTemplateServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

test('isActive returns true when phone forwarding is set', function (): void {
    $user = User::factory()->create(['username' => 'phone.user', 'active' => true]);

    $service = mock(AbwesenheitService::class)->makePartial();
    $service->shouldReceive('show')->once()->andReturn([
        'outlook' => ['status' => 'disabled'],
        'phone' => '1234',
        'd3' => ['abwesend' => false],
        'fetch_errors' => [],
    ]);

    expect($service->isActive($user))->toBeTrue();
});

test('isActive returns false when all channels are inactive', function (): void {
    $user = User::factory()->create(['username' => 'inactive.user', 'active' => true]);

    $service = mock(AbwesenheitService::class)->makePartial();
    $service->shouldReceive('show')->once()->andReturn([
        'outlook' => ['status' => 'disabled'],
        'phone' => '',
        'd3' => ['abwesend' => false],
        'fetch_errors' => [],
    ]);

    expect($service->isActive($user))->toBeFalse();
});

test('isActiveFromShow treats outlook scheduled as active', function (): void {
    $service = app(AbwesenheitService::class);

    expect($service->isActiveFromShow([
        'outlook' => ['status' => 'scheduled'],
        'phone' => '',
        'd3' => ['abwesend' => false],
        'fetch_errors' => [],
    ]))->toBeTrue();
});

test('isActiveFromShow treats unavailable outlook as inactive', function (): void {
    $service = app(AbwesenheitService::class);

    expect($service->isActiveFromShow([
        'outlook' => ['status' => 'unavailable'],
        'phone' => '',
        'd3' => ['abwesend' => false],
        'fetch_errors' => ['outlook' => true],
    ]))->toBeFalse();
});

test('store data uses phone and d3 vertreter fallbacks', function (): void {
    $data = new AbwesenheitStoreData(
        email_vertreter: 'email.user',
        email_delegate: false,
        call_forwarding: true,
        d3_forwarding: true,
        phone_vertreter: 'phone.user',
        d3_vertreter: 'd3.user',
    );

    expect($data->phoneVertreterUsername())->toBe('phone.user')
        ->and($data->d3VertreterUsername())->toBe('d3.user');

    $fallback = new AbwesenheitStoreData(
        email_vertreter: 'email.user',
        email_delegate: false,
        call_forwarding: false,
        d3_forwarding: false,
    );

    expect($fallback->phoneVertreterUsername())->toBe('email.user')
        ->and($fallback->d3VertreterUsername())->toBe('email.user');
});

test('resolveCallDestination extracts extension from telefon', function (): void {
    User::factory()->create([
        'username' => 'tel.user',
        'telefon' => '+49(231)5493-429',
        'active' => true,
    ]);

    $service = app(AbwesenheitService::class);

    expect($service->resolveCallDestination('tel.user'))->toBe('429');
});

test('show includes active mailbox grants with delegate name', function (): void {
    $owner = User::factory()->create([
        'username' => 'mailbox.owner',
        'vorname' => 'Owner',
        'nachname' => 'User',
        'active' => true,
        'linepattern' => null,
    ]);
    $delegate = User::factory()->create([
        'username' => 'mailbox.delegate',
        'vorname' => 'Max',
        'nachname' => 'Vertreter',
        'active' => true,
    ]);

    MailboxGrant::query()->create([
        'user_id' => $owner->id,
        'owner_upn' => $owner->upn,
        'delegate_upn' => $delegate->upn,
        'access_rights' => 'FullAccess',
        'already_existed' => false,
        'granted_at' => now(),
    ]);

    MailboxGrant::query()->create([
        'user_id' => $owner->id,
        'owner_upn' => $owner->upn,
        'delegate_upn' => 'revoked.user@example.test',
        'access_rights' => 'FullAccess',
        'already_existed' => false,
        'granted_at' => now()->subDay(),
        'revoked_at' => now(),
    ]);

    $mailbox = mock(MsGraphMailboxServiceInterface::class);
    $mailbox->shouldReceive('getAutoReplySettings')->once()->andReturn(['status' => 'disabled']);
    app()->instance(MsGraphMailboxServiceInterface::class, $mailbox);

    $d3 = mock(D3ApiInterface::class);
    $d3->shouldReceive('getUserIdByUsername')->once()->andReturn('d3-user-id');
    $d3->shouldReceive('getUserAbsence')->once()->andReturn(['abwesend' => false, 'vertreter' => null]);
    app()->instance(D3ApiInterface::class, $d3);
    config(['intranet-app-abwesenheit.d3_api' => D3ApiInterface::class]);

    $status = app(AbwesenheitService::class)->show($owner);

    expect($status['mailbox']['delegated'])->toBeTrue()
        ->and($status['mailbox']['delegates'])->toHaveCount(1)
        ->and($status['mailbox']['delegates'][0]['upn'])->toBe($delegate->upn)
        ->and($status['mailbox']['delegates'][0]['name'])->toBe($delegate->name);
});

test('show reports no mailbox delegation without active grants', function (): void {
    $owner = User::factory()->create([
        'username' => 'mailbox.none',
        'active' => true,
        'linepattern' => null,
    ]);

    $mailbox = mock(MsGraphMailboxServiceInterface::class);
    $mailbox->shouldReceive('getAutoReplySettings')->once()->andReturn(['status' => 'disabled']);
    app()->instance(MsGraphMailboxServiceInterface::class, $mailbox);

    $d3 = mock(D3ApiInterface::class);
    $d3->shouldReceive('getUserIdByUsername')->once()->andReturn('d3-user-id');
    $d3->shouldReceive('getUserAbsence')->once()->andReturn(['abwesend' => false]);
    app()->instance(D3ApiInterface::class, $d3);
    config(['intranet-app-abwesenheit.d3_api' => D3ApiInterface::class]);

    $status = app(AbwesenheitService::class)->show($owner);

    expect($status['mailbox']['delegated'])->toBeFalse()
        ->and($status['mailbox']['delegates'])->toBe([]);
});

test('apply treats empty d3 success body as ok without warning', function (): void {
    $user = User::factory()->create([
        'username' => 'd3.empty.user',
        'upn' => 'd3.empty.user@example.test',
        'active' => true,
    ]);

    $template = mock(MsGraphOutOfOfficeTemplateServiceInterface::class);
    $template->shouldReceive('getTemplate')->once()->andReturn('ooo');
    app()->instance(MsGraphOutOfOfficeTemplateServiceInterface::class, $template);

    $mailbox = mock(MsGraphMailboxServiceInterface::class);
    $mailbox->shouldReceive('setOutOfOffice')->once();
    app()->instance(MsGraphMailboxServiceInterface::class, $mailbox);

    $d3 = mock(D3ApiInterface::class);
    $d3->shouldReceive('setUserAbsence')
        ->once()
        ->andReturn([
            'userId' => 'user-id',
            'isAbsent' => true,
            'deputyId' => 'deputy-id',
        ]);
    app()->instance(D3ApiInterface::class, $d3);
    config(['intranet-app-abwesenheit.d3_api' => D3ApiInterface::class]);

    $result = app(AbwesenheitService::class)->apply($user, new AbwesenheitStoreData(
        email_vertreter: 'vertreter.user',
        email_delegate: false,
        call_forwarding: false,
        d3_forwarding: true,
        d3_vertreter: 'vertreter.user',
    ));

    expect($result->warnings)->toBe([]);
});

test('apply treats null d3 response as ok without warning', function (): void {
    $user = User::factory()->create([
        'username' => 'd3.null.user',
        'upn' => 'd3.null.user@example.test',
        'active' => true,
    ]);

    $template = mock(MsGraphOutOfOfficeTemplateServiceInterface::class);
    $template->shouldReceive('getTemplate')->once()->andReturn('ooo');
    app()->instance(MsGraphOutOfOfficeTemplateServiceInterface::class, $template);

    $mailbox = mock(MsGraphMailboxServiceInterface::class);
    $mailbox->shouldReceive('setOutOfOffice')->once();
    app()->instance(MsGraphMailboxServiceInterface::class, $mailbox);

    $d3 = mock(D3ApiInterface::class);
    $d3->shouldReceive('setUserAbsence')->once()->andReturn(null);
    app()->instance(D3ApiInterface::class, $d3);
    config(['intranet-app-abwesenheit.d3_api' => D3ApiInterface::class]);

    $result = app(AbwesenheitService::class)->apply($user, new AbwesenheitStoreData(
        email_vertreter: 'vertreter.user',
        email_delegate: false,
        call_forwarding: false,
        d3_forwarding: true,
        d3_vertreter: 'vertreter.user',
    ));

    expect($result->warnings)->toBe([]);
});

test('apply warns on unexpected d3 response shape', function (): void {
    $user = User::factory()->create([
        'username' => 'd3.bad.user',
        'upn' => 'd3.bad.user@example.test',
        'active' => true,
    ]);

    $template = mock(MsGraphOutOfOfficeTemplateServiceInterface::class);
    $template->shouldReceive('getTemplate')->once()->andReturn('ooo');
    app()->instance(MsGraphOutOfOfficeTemplateServiceInterface::class, $template);

    $mailbox = mock(MsGraphMailboxServiceInterface::class);
    $mailbox->shouldReceive('setOutOfOffice')->once();
    app()->instance(MsGraphMailboxServiceInterface::class, $mailbox);

    $d3 = mock(D3ApiInterface::class);
    $d3->shouldReceive('setUserAbsence')->once()->andReturn(['error' => 'conflict']);
    app()->instance(D3ApiInterface::class, $d3);
    config(['intranet-app-abwesenheit.d3_api' => D3ApiInterface::class]);

    $result = app(AbwesenheitService::class)->apply($user, new AbwesenheitStoreData(
        email_vertreter: 'vertreter.user',
        email_delegate: false,
        call_forwarding: false,
        d3_forwarding: true,
        d3_vertreter: 'vertreter.user',
    ));

    expect($result->warnings)->toHaveCount(1)
        ->and($result->warnings[0])->toContain('Vertretung in d3');
});
