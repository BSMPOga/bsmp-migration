<?php

namespace App\Jobs;

use App\Http\Controllers\UserController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunMigration implements ShouldQueue
{

    use Queueable;

    public $timeout = 0; // no timeout — run until finished
    public $tries   = 1;

    public function __construct(public array $companyIds) {}

    public function handle(): void
    {
        $controller = new UserController();

        $steps = [
            'users'              => 'moveUsers',
            'payees'             => 'migratePayee',
            'groups'             => 'migrateGroups',
            'approver_circles'   => 'moveApprovalCircle',
            'expense_categories' => 'moveExpenseCategories',
            'payments'           => 'movePayments',
            'purchases'          => 'movePurchases',
            'wallet'             => 'moveWallet',
            'billing'            => 'moveBilling',
            'transactions'       => 'moveTransactions',
        ];

        foreach ($this->companyIds as $id) {
            $controller->company_id  = $id;
            $controller->new_company = '';

            Log::info("Migration started for company [{$id}]");

            try {
                $controller->new_company = $controller->migrateCompany();
                Log::info("Migration company [{$id}] step [company] done: {$controller->new_company}");
            } catch (\Throwable $e) {
                Log::error("Migration company [{$id}] step [company] failed: " . $e->getMessage());
                continue;
            }

            foreach ($steps as $key => $method) {
                try {
                    $controller->$method();
                    Log::info("Migration company [{$id}] step [{$key}] done");
                } catch (\Throwable $e) {
                    Log::error("Migration company [{$id}] step [{$key}] failed: " . $e->getMessage());
                    break;
                }
            }

            Log::info("Migration finished for company [{$id}]");
        }
    }
}
