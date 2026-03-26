<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/migrate-all', [UserController::class, 'migrateAll']);
Route::get('/migrate-company', [UserController::class, 'migrateCompany']);
Route::get('/migrate-user', [UserController::class, 'moveUsers']);
Route::get('/migrate-payee', [UserController::class, 'migratePayee']);
Route::get('/migrate-groups', [UserController::class, 'migrateGroups']);
Route::get('/migrate-approver-circle', [UserController::class, 'moveApprovalCircle']);
Route::get('/migrate-expense-category', [UserController::class, 'moveExpenseCategories']);
Route::get('/migrate-payments', [UserController::class, 'movePayments']);
Route::get('/migrate-purchases', [UserController::class, 'movePurchases']);
Route::get('/migrate-wallet', [UserController::class, 'moveWallet']);
Route::get('/migrate-billing', [UserController::class, 'moveBilling']);
Route::get('/migrate-transactions', [UserController::class, 'moveTransactions']);
