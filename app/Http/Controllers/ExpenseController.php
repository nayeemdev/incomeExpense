<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index()
    {
        $data['expenses'] = Expense::where('user_id', Auth::user()->id)->latest()->get();

        return view('pages.expenses.index', $data);
    }

    public function create()
    {
        return view('pages.expenses.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'expense_title' => 'required',
            'expense_amount' => 'required',
            'expense_date'=> 'required'
        ]);

        $expense = new Expense();
        $expense->expense_title = $request->expense_title;
        $expense->expense_amount = $request->expense_amount;
        $expense->expense_date = $request->expense_date;
        $expense->user_id = Auth::user()->id;
        $expense->save();

        return redirect('/expense')->with('message', 'New Expense Added');
    }
}
