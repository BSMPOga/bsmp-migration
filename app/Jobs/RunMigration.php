<?php

namespace App\Jobs;

use App\Http\Controllers\UserController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

        // foreach ($this->companyIds as $id) {
        //     $controller->company_id  = $id;
        //     $controller->new_company = '';

        //     $company = DB::connection('mysql2')->table('companies')->where('old_company_id', $id)->first();
        //     if (!$company) {
        //         Log::error("Company with old_company_id [{$id}] not found in the database.");
        //         continue;
        //     }
        //     $controller->new_company = $company->id;
        //     Log::info("Wallet Migration started for company [{$id}]");
        //     try {
        //         $controller->moveWallet();
        //         Log::info("Wallet Migration company [{$id}] done");
        //     } catch (\Throwable $e) {
        //         Log::error("Wallet Migration company [{$id}] failed: " . $e->getMessage());
        //         break;
        //     }

        //     Log::info("Payee Migration started for company [{$id}]");
        //     try {
        //         $controller->migratePayee();
        //         Log::info("Payee Migration company [{$id}] done");
        //     } catch (\Throwable $e) {
        //         Log::error("Payee Migration company [{$id}] failed: " . $e->getMessage());
        //         break;
        //     }

        //     // Log::info("Expense Category Migration started for company [{$id}]");
        //     // try {
        //     //     $controller->moveExpenseCategories();
        //     //     Log::info("Expense Category Migration company [{$id}] done");
        //     // } catch (\Throwable $e) {
        //     //     Log::error("Expense Category Migration company [{$id}] failed: " . $e->getMessage());
        //     //     break;
        //     // }

        //     Log::info("Purchase Migration started for company [{$id}]");
        //     try {
        //         $controller->movePurchases();
        //         Log::info("Purchase Migration company [{$id}] done");
        //     } catch (\Throwable $e) {
        //         Log::error("Purchase Migration company [{$id}] failed: " . $e->getMessage());
        //         break;
        //     }
        //     Log::info("Payment Migration started for company [{$id}]");
        //     try {
        //         $controller->movePayments();
        //         Log::info("Payment Migration company [{$id}] done");
        //     } catch (\Throwable $e) {
        //         Log::error("Payment Migration company [{$id}] failed: " . $e->getMessage());
        //         break;
        //     }

        //     Log::info("Transaction Migration started for company [{$id}]");
        //     try {
        //         $controller->moveTransactions();
        //         Log::info("Transaction Migration company [{$id}] done");
        //     } catch (\Throwable $e) {
        //         Log::error("Transaction Migration company [{$id}] failed: " . $e->getMessage());
        //         break;
        //     }
        // }

        // Log::info("Transaction Migration finished completely");

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
