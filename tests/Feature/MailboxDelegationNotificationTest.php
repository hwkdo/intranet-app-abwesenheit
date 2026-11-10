<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppAbwesenheit\IntranetAppAbwesenheit;
use Hwkdo\IntranetAppAbwesenheit\Notifications\MailboxDelegationGrantedNotification;
use Hwkdo\IntranetAppAbwesenheit\Notifications\MailboxDelegationRevokedNotification;
use Hwkdo\IntranetAppBase\Livewire\NotificationSettings;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    Permission::findOrCreate('see-app-abwesenheit', 'web');
});

test('postfach benachrichtigungen sind pflicht mit inbox und mail', function (): void {
    $types = collect(IntranetAppAbwesenheit::notificationTypes())->keyBy('key');

    expect($types->keys()->all())->toBe([
        'abwesenheit.mailbox_delegation_granted',
        'abwesenheit.mailbox_delegation_revoked',
    ])
        ->and($types['abwesenheit.mailbox_delegation_granted']->mandatory)->toBeTrue()
        ->and($types['abwesenheit.mailbox_delegation_granted']->requiredChannels)->toBe(['inbox', 'mail'])
        ->and($types['abwesenheit.mailbox_delegation_revoked']->defaultChannels)->toBe(['inbox', 'mail'])
        ->and($types['abwesenheit.mailbox_delegation_revoked']->channelIsRequired('mail'))->toBeTrue();
});

test('benachrichtigungstexte unterscheiden inhaber und vertreter', function (): void {
    $owner = User::factory()->create(['active' => true]);
    $grantedOwner = new MailboxDelegationGrantedNotification(forDelegate: false, ownerName: 'Ada Lovelace', delegateName: 'Grace Hopper');
    $grantedDelegate = new MailboxDelegationGrantedNotification(forDelegate: true, ownerName: 'Ada Lovelace', delegateName: 'Grace Hopper');
    $revokedOwner = new MailboxDelegationRevokedNotification(forDelegate: false, ownerName: 'Ada Lovelace', delegateName: 'Grace Hopper');
    $revokedDelegate = new MailboxDelegationRevokedNotification(forDelegate: true, ownerName: 'Ada Lovelace', delegateName: 'Grace Hopper');

    $grantedOwnerPayload = $grantedOwner->toArray($owner);
    $grantedDelegatePayload = $grantedDelegate->toArray($owner);

    expect($grantedOwnerPayload['title'])->toBe('Postfach-Zugriff erteilt')
        ->and($grantedOwnerPayload['body'])->toBe('Grace Hopper hat jetzt Vollzugriff auf Ihr Outlook-Postfach.')
        ->and($grantedOwnerPayload['url'])->toBe(route('apps.abwesenheit.meine'))
        ->and($grantedDelegatePayload['title'])->toBe('Postfach-Zugriff erhalten')
        ->and($grantedDelegatePayload['body'])->toBe('Sie haben jetzt Vollzugriff auf das Outlook-Postfach von Ada Lovelace.')
        ->and($grantedDelegatePayload['url'])->toBeNull()
        ->and($revokedOwner->toArray($owner)['body'])->toBe('Der Vollzugriff von Grace Hopper auf Ihr Outlook-Postfach wurde entfernt.')
        ->and($revokedDelegate->toArray($owner)['body'])->toBe('Ihr Vollzugriff auf das Outlook-Postfach von Ada Lovelace wurde entfernt.')
        ->and($grantedOwner->toMail($owner)->subject)->toBe('Postfach-Zugriff erteilt')
        ->and($revokedDelegate->toMail($owner)->subject)->toBe('Postfach-Zugriff entzogen');
});

test('pflichtkanaele der postfach benachrichtigung bleiben an', function (): void {
    $user = User::factory()->create(['active' => true]);

    $component = Livewire::actingAs($user)->test(NotificationSettings::class);
    $component->call('toggleChannel', 'abwesenheit.mailbox_delegation_granted', 'mail');
    $component->call('toggleChannel', 'abwesenheit.mailbox_delegation_granted', 'inbox');

    $wireKey = NotificationSettings::toWireKey('abwesenheit.mailbox_delegation_granted');
    $channels = $component->get('preferences')[$wireKey]['channels'];

    expect($channels)->toContain('inbox')
        ->and($channels)->toContain('mail');

    $component->assertSee('Dieser Kanal ist verpflichtend und kann nicht abgewählt werden.');
});

test('benachrichtigungsseite ist fuer app nutzer erreichbar', function (): void {
    $user = User::factory()->create(['active' => true]);
    $user->givePermissionTo('see-app-abwesenheit');

    $this->actingAs($user)
        ->get(route('apps.abwesenheit.settings.notifications'))
        ->assertOk()
        ->assertSee('Postfach-Zugriff erteilt')
        ->assertSee('Postfach-Zugriff entfernt');
});

test('benachrichtigungsseite bleibt ohne berechtigung geschlossen', function (): void {
    $user = User::factory()->create(['active' => true]);

    $this->actingAs($user)
        ->get(route('apps.abwesenheit.settings.notifications'))
        ->assertForbidden();
});
