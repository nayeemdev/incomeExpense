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

    public function index($period='')
    {
        if($period=='') { $period = date('Y-m'); }

        $month = substr($period,5,2) *1;
        $year = substr($period,0,4)*1;
        $data['incomes'] = Income::where('user_id', Auth::User()->id)->whereYear('income_date', $year)->whereMonth('income_date', $month)->sum('income_amount');
        $data['expenses'] = Expense::where('user_id', Auth::User()->id)->whereYear('expense_date', $year)->whereMonth('expense_date', $month)->sum('expense_amount');
        $data['balance'] = $data['incomes'] - $data['expenses'];

        // find first entry, to be use for list_period()
        $first_expense = Expense::where('user_id', Auth::User()->id)->orderBy('expense_date','desc')->limit(1)->first();
        $first_income = Income::where('user_id', Auth::User()->id)->orderBy('income_date','desc')->limit(1)->first();
        if($first_income->income_date>$first_expense->expense_date) {
            $until = $first_expense->expense_date;
        } else {
            $until = $first_income->income_date;
        }

        $data['list_period'] = list_period(substr($until,0,10));
        $data['list_period'][date("Y-m")] = "This Month";
        $data['period'] = $period;

        return view('pages.dashboard', $data);
    }

    public function summary()
    {
        $incomes = Income::where('user_id', Auth::User()->id)->get()->toArray();
        $expenses = Expense::where('user_id', Auth::User()->id)->get()->toArray();
        foreach ($incomes as $key => $value) {
            $incomes[$key]['type'] = 'income';
        }

        foreach ($expenses as $key => $value) {
            $expenses[$key]['type'] = 'expense';
        }

        $data['results'] = array_merge($incomes, $expenses);
        $data['total_income'] = Income::where('user_id', Auth::User()->id)->sum('income_amount');
        $data['total_expense'] = Expense::where('user_id', Auth::User()->id)->sum('expense_amount');
        $data['balance'] = $data['total_income'] - $data['total_expense'];

        return view('pages.summary', $data);
    }
}
