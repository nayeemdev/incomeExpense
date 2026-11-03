<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\TransactionGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

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
    }
}
