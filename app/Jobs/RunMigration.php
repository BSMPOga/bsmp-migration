<?php

namespace App\Jobs;

use App\Http\Controllers\UserController;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunMigration implements ShouldQueue
{
    // [531,532,533,534,535,536,539,540,541,542,545,546,547,548,552,556,562,564,568,573,574,529,430,332,525,428,331];
    // [234, 258, 259, 265, 413, 444, 489, 144, 148, 154, 164, 174, 184, 255, 256, 257, 260, 266, 267, 270, 271,
    //272, 275, 277, 289, 290, 291, 313, 319, 320, 321, 323, 324, 325, 326, 327, 328, 330, 335, 338, 339, 340, 341,
    // 342, 343, 344, 345, 347, 348, 350, 351, 357, 360, 361, 362, 367, 368, 369, 370, 399, 400, 401, 403, 404, 405,
    // 409, 410, 412, 414, 415, 427, 439, 440, 445, 446, 447, 448, 449, 450, 451, 454, 455, 456, 457, 458, 461, 463,
    // 464, 465, 467, 468, 469, 495, 519, 522, 524]
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
