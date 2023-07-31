<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index($period='')
    {
        if($period=='') { $period = date('Y-m'); }
        $month = substr($period,5,2) *1;
        $year = substr($period,0,4)*1;

        $data['expenses'] = Expense::where('user_id', Auth::user()->id)->whereYear('expense_date',$year)->whereMonth('expense_date',$month)->latest()->paginate(12);
        $data['totalExpenses'] = Expense::where('user_id', Auth::user()->id)->whereYear('expense_date',$year)->whereMonth('expense_date',$month)->sum('expense_amount');

        // find first entry, to be use for list_period()
        $first_expense = Expense::where('user_id', Auth::User()->id)->orderBy('expense_date','asc')->limit(1)->first();

        $data['list_period'] = list_period(substr($first_expense->expense_date,0,10));
        $data['list_period'][date("Y-m")] = "This Month";
        $data['period'] = $period;

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

    public function edit($id)
    {
        $data['expense'] = Expense::findOrFail($id);
        return view('pages.expenses.edit', $data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'expense_title' => 'required',
            'expense_amount' => 'required',
            'expense_date'=> 'required'
        ]);

        $expense = Expense::findOrFail($request->expense_id);
        $expense->expense_title = $request->expense_title;
        $expense->expense_amount = $request->expense_amount;
        $expense->expense_date = $request->expense_date;
        $expense->update();

        return redirect('/expense')->with('message', 'Expense details updated successfully');
    }

    public function destroy($id)
    {
        Expense::findOrFail($id)->delete();
        return back()->with('message', 'Expense details deleted successfully');
    }
}
