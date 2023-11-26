<?php

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

Route::get('/', 'HomeController@index')->name('index');
Route::get('/summaries', 'HomeController@summary')->name('summary');
Route::get('/monthly/index', 'HomeController@monthly')->name('monthly.index');

//Notes Route
Route::get('/notes', 'NoteController@index')->name('notes.index');
Route::get('/notes/create', 'NoteController@create')->name('notes.create');
Route::post('/notes/store', 'NoteController@store')->name('notes.store');
Route::get('/notes/edit/{id}', 'NoteController@edit')->name('notes.edit');
Route::post('/notes/update', 'NoteController@update')->name('notes.update');
Route::get('/notes/delete/{id}', 'NoteController@destroy')->name('notes.delete');


//Income Route
Route::get('/incomes', 'IncomeController@index')->name('incomes.index');
Route::get('/incomes/create', 'IncomeController@create')->name('incomes.create');
Route::post('/incomes/store', 'IncomeController@store')->name('incomes.store');
Route::get('/incomes/edit/{id}', 'IncomeController@edit')->name('incomes.edit');
Route::post('/incomes/update', 'IncomeController@update')->name('incomes.update');
Route::get('/incomes/delete/{id}', 'IncomeController@destroy')->name('incomes.delete');

//Expense Route
Route::get('/expense', 'ExpenseController@index')->name('expense.index');
Route::get('/expense/create', 'ExpenseController@create')->name('expense.create');
Route::post('/expense/store', 'ExpenseController@store')->name('expense.store');
Route::get('/expense/edit/{id}', 'ExpenseController@edit')->name('expenses.edit');
Route::post('/expense/update', 'ExpenseController@update')->name('expenses.update');
Route::get('/expense/delete/{id}', 'ExpenseController@destroy')->name('expenses.delete');
