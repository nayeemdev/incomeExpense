<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
Use Auth;
use Illuminate\support\Carbon;
Use App\Models\Income;
Use App\Models\Expense;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {

        $data['incomes'] = Income::where('user_id', Auth::User()->id)->whereYear('income_date', Carbon::now()->year)->whereMonth('income_date', Carbon::now()->month)->sum('income_amount');
        $data['expenses'] = Expense::where('user_id', Auth::User()->id)->whereYear('expense_date', Carbon::now()->year)->whereMonth('expense_date', Carbon::now()->month)->sum('expense_amount');
        $data['balance'] = $data['incomes'] - $data['expenses'];

        return view('pages.dashboard', $data);
    }

    public function summary()
    {
        $userId = Auth::User()->id;

        // Fetch incomes and expenses as collections
        $incomes = Income::where('user_id', $userId)->orderby('income_date', 'asc')->get();
        $expenses = Expense::where('user_id', $userId)->orderBy('expense_date', 'desc')->get();

        // Add type to each entry
        $incomes->each(function ($item) {
            $item->type = 'income';
        });

        $expenses->each(function ($item) {
            $item->type = 'expense';
        });

        // Merge collections and sort them by date
        $results = $incomes->merge($expenses)->sortByDesc('transaction_date')->values();


        // Total income, expense, and balance calculations
        $totalIncome = Income::where('user_id', $userId)->sum('income_amount');
        $totalExpense = Expense::where('user_id', $userId)->sum('expense_amount');
        $balance = $totalIncome - $totalExpense;

        // Prepare data for the view
        $data = [
            'results' => $results,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $balance,
        ];

        return view('pages.summary', $data);
    }
}
