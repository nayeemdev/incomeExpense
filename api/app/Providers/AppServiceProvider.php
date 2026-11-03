<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\PersonalAccessToken;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'account' => Account::class,
            'transaction_group' => TransactionGroup::class,
            'user' => User::class,
        ]);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Gate::define('admin', fn (User $user) => $user->is_admin);

        SystemSettings::apply();
        // Queue workers run for days, so pick up changes the admin made since they started.
        Queue::before(fn () => SystemSettings::apply());
    }
}
