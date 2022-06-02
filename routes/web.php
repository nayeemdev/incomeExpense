<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Auth::routes();

Route::get('/', [HomeController::class, 'index'])->name('index');
Route::get('/summaries', [HomeController::class, 'summary'])->name('summary');
Route::get('/monthly/index', [HomeController::class, 'monthly'])->name('monthly.index');

//Notes Route
Route::resource('notes', NoteController::class)->except(['show']);
Route::resource('incomes', IncomeController::class)->except(['show']);
Route::resource('expenses', ExpenseController::class)->except(['show']);

