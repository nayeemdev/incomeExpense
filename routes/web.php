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
Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
Route::get('/notes/create', [NoteController::class, 'create'])->name('notes.create');
Route::post('/notes/store', [NoteController::class, 'store'])->name('notes.store');
Route::get('/notes/edit/{id}', [NoteController::class, 'edit'])->name('notes.edit');
Route::post('/notes/update', [NoteController::class, 'update'])->name('notes.update');
Route::get('/notes/delete/{id}', [NoteController::class, 'destroy'])->name('notes.delete');

//Income Route
Route::get('/incomes', [IncomeController::class, 'index'])->name('incomes.index');
Route::get('/incomes/create', [IncomeController::class, 'create'])->name('incomes.create');
Route::post('/incomes/store', [IncomeController::class, 'store'])->name('incomes.store');
Route::get('/incomes/edit/{id}', [IncomeController::class, 'edit'])->name('incomes.edit');
Route::post('/incomes/update', [IncomeController::class, 'update'])->name('incomes.update');
Route::get('/incomes/delete/{id}', [IncomeController::class, 'destroy'])->name('incomes.delete');

//Expense Route
Route::get('/expense', [ExpenseController::class, 'index'])->name('expense.index');
Route::get('/expense/create', [ExpenseController::class, 'create'])->name('expense.create');
Route::post('/expense/store', [ExpenseController::class, 'store'])->name('expense.store');
Route::get('/expense/edit/{id}', [ExpenseController::class, 'edit'])->name('expenses.edit');
Route::post('/expense/update', [ExpenseController::class, 'update'])->name('expenses.update');
Route::get('/expense/delete/{id}', [ExpenseController::class, 'destroy'])->name('expenses.delete');
