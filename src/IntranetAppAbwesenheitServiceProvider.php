<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit;

use Hwkdo\IntranetAppAbwesenheit\Commands\ProcessAbwesenheitSchedulesCommand;
use Hwkdo\IntranetAppAbwesenheit\Policies\AbwesenheitPolicy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppAbwesenheitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('intranet-app-abwesenheit')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(ProcessAbwesenheitSchedulesCommand::class)
            ->discoversMigrations();
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerAbwesenheitGates();

        $this->app->booted(function (): void {
            Volt::mount(__DIR__.'/../resources/views/livewire');
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->resolving(Schedule::class, function (): void {
            require __DIR__.'/../routes/console.php';
        });
    }

    protected function registerAbwesenheitGates(): void
    {
        $userModel = config('intranet-app-abwesenheit.user_model');
        $policy = AbwesenheitPolicy::class;

        Gate::define('abwesenheit.view', function ($actor, $target) use ($userModel, $policy): bool {
            if (! $actor instanceof $userModel || ! $target instanceof $userModel) {
                return false;
            }

            return app($policy)->view($actor, $target);
        });

        Gate::define('abwesenheit.manage', function ($actor, $target) use ($userModel, $policy): bool {
            if (! $actor instanceof $userModel || ! $target instanceof $userModel) {
                return false;
            }

            return app($policy)->manage($actor, $target);
        });

        Gate::define('abwesenheit.delegate-mailbox-for', function ($actor, $target) use ($userModel, $policy): bool {
            if (! $actor instanceof $userModel || ! $target instanceof $userModel) {
                return false;
            }

            return app($policy)->delegateMailboxFor($actor, $target);
        });
    }
}
