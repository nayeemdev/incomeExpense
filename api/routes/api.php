<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\AuthConfigController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\AutocompleteController;
use App\Http\Controllers\AvailableBudgetController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BudgetLimitController;
use App\Http\Controllers\BulkTransactionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\LedgerInvitationController;
use App\Http\Controllers\LedgerMemberController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ObjectGroupController;
use App\Http\Controllers\PersonalAccessTokenController;
use App\Http\Controllers\PiggyBankController;
use App\Http\Controllers\QuickEntryController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\RecurrenceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\RuleGroupController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionLinkController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('auth/config', AuthConfigController::class);
Route::post('auth/tokens', [TokenController::class, 'store'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('auth/tokens/current', [TokenController::class, 'destroy']);

    Route::middleware('ability:*,quick-entry')->group(function () {
        Route::get('ledgers', [LedgerController::class, 'index']);

        Route::prefix('ledgers/{ledger}')->middleware('can:view,ledger')->group(function () {
            Route::get('summary', SummaryController::class);
            Route::get('accounts', [AccountController::class, 'index']);
            Route::get('categories', [CategoryController::class, 'index']);
            Route::get('autocomplete/transactions', [AutocompleteController::class, 'transactions']);
            Route::get('autocomplete/frequent', [AutocompleteController::class, 'frequent']);
            Route::post('quick-entry', QuickEntryController::class)->middleware('can:edit,ledger');
        });
    });

    Route::middleware('abilities:*')->group(function () {
        Route::get('user', [UserController::class, 'show']);
        Route::patch('user/preferences', [UserController::class, 'updatePreferences']);

        Route::get('tokens', [PersonalAccessTokenController::class, 'index']);
        Route::post('tokens', [PersonalAccessTokenController::class, 'store']);
        Route::delete('tokens/{token}', [PersonalAccessTokenController::class, 'destroy']);

        Route::get('currencies', [CurrencyController::class, 'index']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::get('notification-settings', [NotificationController::class, 'settings']);
        Route::patch('notification-settings', [NotificationController::class, 'updateSettings']);
        Route::post('notification-settings/ntfy-test', [NotificationController::class, 'testNtfy']);
        Route::post('push-subscriptions', [NotificationController::class, 'subscribe']);
        Route::delete('push-subscriptions', [NotificationController::class, 'unsubscribe']);
        Route::get('rule-options', [RuleController::class, 'options']);

        Route::prefix('admin')->middleware('can:admin')->group(function () {
            Route::get('settings', [SystemSettingsController::class, 'show']);
            Route::patch('settings', [SystemSettingsController::class, 'update']);
            Route::post('settings/test-mail', [SystemSettingsController::class, 'testMail']);
        });
        Route::get('category-styles', [CategoryController::class, 'styles']);

        Route::get('invitations/{token}', [InvitationController::class, 'show']);
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

        Route::post('ledgers', [LedgerController::class, 'store']);

        Route::prefix('ledgers/{ledger}')->middleware('can:view,ledger')->scopeBindings()->group(function () {
            Route::get('/', [LedgerController::class, 'show']);
            Route::patch('membership', [MembershipController::class, 'update']);
            Route::get('members', [LedgerMemberController::class, 'index']);
            Route::delete('members/{member}', [LedgerMemberController::class, 'destroy']);

            Route::get('accounts/{account}', [AccountController::class, 'show']);
            Route::get('categories/{category}', [CategoryController::class, 'show']);
            Route::apiResource('tags', TagController::class)->only(['index', 'show']);
            Route::apiResource('transactions', TransactionController::class)->only(['index', 'show'])
                ->parameters(['transactions' => 'transactionGroup']);
            Route::apiResource('budgets', BudgetController::class)->only(['index', 'show']);
            Route::get('available-budgets', [AvailableBudgetController::class, 'index']);
            Route::apiResource('bills', BillController::class)->only(['index', 'show']);
            Route::apiResource('piggy-banks', PiggyBankController::class)->only(['index', 'show']);
            Route::apiResource('investments', InvestmentController::class)->only(['index', 'show']);
            Route::apiResource('recurrences', RecurrenceController::class)->only(['index', 'show']);
            Route::get('object-groups', [ObjectGroupController::class, 'index']);
            Route::get('link-types', [TransactionLinkController::class, 'linkTypes']);
            Route::get('transactions/{transactionGroup}/links', [TransactionLinkController::class, 'index']);
            Route::get('exchange-rates', [ExchangeRateController::class, 'index']);
            Route::get('export/transactions.csv', [ExportController::class, 'transactions']);
            Route::get('export/backup.json', [ExportController::class, 'backup']);
            Route::get('reports/overview', [ReportController::class, 'overview']);
            Route::get('reports/net-worth', [ReportController::class, 'netWorth']);
            Route::get('reports/by/{groupBy}', [ReportController::class, 'breakdown']);
            Route::get('accounts/{account}/balance-history', [ReportController::class, 'accountBalance']);
            Route::get('saved-searches', [SavedSearchController::class, 'index']);
            Route::post('saved-searches', [SavedSearchController::class, 'store']);
            Route::delete('saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy']);
            Route::get('rule-groups', [RuleGroupController::class, 'index']);
            Route::get('rules/{rule}', [RuleController::class, 'show']);
            Route::post('rules/{rule}/test', [RuleController::class, 'test']);
            Route::get('attachments', [AttachmentController::class, 'index']);
            Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);

            Route::middleware('can:edit,ledger')->group(function () {
                Route::apiResource('accounts', AccountController::class)->only(['store', 'update', 'destroy']);
                Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
                Route::apiResource('tags', TagController::class)->only(['store', 'update', 'destroy']);
                Route::apiResource('transactions', TransactionController::class)->only(['store', 'update', 'destroy'])
                    ->parameters(['transactions' => 'transactionGroup']);
                Route::post('transactions/{group}/restore', [TransactionController::class, 'restore']);
                Route::post('accounts/{account}/reconcile', ReconciliationController::class);
                Route::apiResource('budgets', BudgetController::class)->only(['store', 'update', 'destroy']);
                Route::post('budgets/{budget}/limits', [BudgetLimitController::class, 'store']);
                Route::patch('budgets/{budget}/limits/{limit}', [BudgetLimitController::class, 'update']);
                Route::delete('budgets/{budget}/limits/{limit}', [BudgetLimitController::class, 'destroy']);
                Route::post('available-budgets', [AvailableBudgetController::class, 'store']);
                Route::delete('available-budgets/{availableBudget}', [AvailableBudgetController::class, 'destroy']);
                Route::apiResource('bills', BillController::class)->only(['store', 'update', 'destroy']);
                Route::apiResource('piggy-banks', PiggyBankController::class)->only(['store', 'update', 'destroy']);
                Route::post('piggy-banks/{piggyBank}/move', [PiggyBankController::class, 'move']);
                Route::apiResource('investments', InvestmentController::class)->only(['store', 'update', 'destroy']);
                Route::post('investments/{investment}/events', [InvestmentController::class, 'storeEvent']);
                Route::delete('investments/{investment}/events/{event}', [InvestmentController::class, 'destroyEvent']);
                Route::apiResource('recurrences', RecurrenceController::class)->only(['store', 'update', 'destroy']);
                Route::post('recurrences/{recurrence}/trigger', [RecurrenceController::class, 'trigger']);
                Route::patch('object-groups/{objectGroup}', [ObjectGroupController::class, 'update']);
                Route::delete('object-groups/{objectGroup}', [ObjectGroupController::class, 'destroy']);
                Route::post('link-types', [TransactionLinkController::class, 'storeLinkType']);
                Route::delete('link-types/{linkType}', [TransactionLinkController::class, 'destroyLinkType']);
                Route::post('transactions/{transactionGroup}/links', [TransactionLinkController::class, 'store']);
                Route::delete('links/{transactionLink}', [TransactionLinkController::class, 'destroy']);
                Route::post('transactions/bulk', BulkTransactionController::class);
                Route::get('imports', [ImportController::class, 'index']);
                Route::post('imports', [ImportController::class, 'store']);
                Route::get('imports/template.csv', [ImportController::class, 'template']);
                Route::get('imports/{import}', [ImportController::class, 'show']);
                Route::post('imports/{import}/preview', [ImportController::class, 'preview']);
                Route::post('imports/{import}/run', [ImportController::class, 'run']);
                Route::delete('imports/{import}', [ImportController::class, 'destroy']);
                Route::delete('import-profiles/{profile}', [ImportController::class, 'destroyProfile']);
                Route::post('exchange-rates', [ExchangeRateController::class, 'store']);
                Route::delete('exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'destroy']);
                Route::post('exchange-rates/fetch', [ExchangeRateController::class, 'fetch']);
                Route::post('rule-groups', [RuleGroupController::class, 'store']);
                Route::patch('rule-groups/{ruleGroup}', [RuleGroupController::class, 'update']);
                Route::delete('rule-groups/{ruleGroup}', [RuleGroupController::class, 'destroy']);
                Route::post('rules', [RuleController::class, 'store']);
                Route::put('rules/{rule}', [RuleController::class, 'update']);
                Route::delete('rules/{rule}', [RuleController::class, 'destroy']);
                Route::post('rules/{rule}/apply', [RuleController::class, 'apply']);
                Route::post('attachments', [AttachmentController::class, 'store']);
                Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);
            });

            Route::middleware('can:manage,ledger')->group(function () {
                Route::patch('/', [LedgerController::class, 'update']);
                Route::delete('/', [LedgerController::class, 'destroy']);
                Route::patch('members/{member}', [LedgerMemberController::class, 'update']);
                Route::get('invitations', [LedgerInvitationController::class, 'index']);
                Route::post('invitations', [LedgerInvitationController::class, 'store']);
                Route::delete('invitations/{invitation}', [LedgerInvitationController::class, 'destroy']);
                Route::get('webhooks', [WebhookController::class, 'index']);
                Route::post('webhooks', [WebhookController::class, 'store']);
                Route::patch('webhooks/{webhook}', [WebhookController::class, 'update']);
                Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy']);
                Route::post('webhooks/{webhook}/secret', [WebhookController::class, 'secret']);
                Route::get('webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries']);
                Route::post('webhooks/{webhook}/deliveries/{delivery}/redeliver', [WebhookController::class, 'redeliver']);
            });
        });
    });
});
