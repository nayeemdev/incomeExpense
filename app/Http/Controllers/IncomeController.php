<?php

namespace App\Http\Controllers;

use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index($period='')
    {
        if($period=='') { $period = date('Y-m'); }
        $month = substr($period,5,2) *1;
        $year = substr($period,0,4)*1;

        $data['incomes'] = Income::where('user_id', Auth::user()->id)->whereYear('income_date',$year)->whereMonth('income_date',$month)->latest()->paginate(12);
        $data['totalIncomes'] = Income::where('user_id', Auth::user()->id)->whereYear('income_date',$year)->whereMonth('income_date',$month)->sum('income_amount');

        // find first entry, to be use for list_period()
        $first_income = Income::where('user_id', Auth::User()->id)->orderBy('income_date','asc')->limit(1)->first();

        $data['list_period'] = list_period(substr($first_income->income_date,0,10));
        $data['list_period'][date("Y-m")] = "This Month";
        $data['period'] = $period;

        return view('pages.incomes.index', $data);
    }

    public function create()
    {
        return view('pages.incomes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'income_title' => 'required',
            'income_amount' => 'required',
            'income_date'=> 'required'
        ]);

        $incomes = new Income();
        $incomes->income_title = $request->income_title;
        $incomes->income_amount = $request->income_amount;
        $incomes->income_date = $request->income_date;
        $incomes->user_id = Auth::user()->id;
        $incomes->save();

        return redirect('/incomes')->with('message', 'New Income Added');
    }

    public function edit($id)
    {
        $data['income'] = Income::findOrFail($id);
        return view('pages.incomes.edit', $data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'income_title' => 'required',
            'income_amount' => 'required',
            'income_date'=> 'required'
        ]);

        $income = Income::findOrFail($request->income_id);
        $income->income_title = $request->income_title;
        $income->income_amount = $request->income_amount;
        $income->income_date = $request->income_date;
        $income->update();

        return redirect('/incomes')->with('message', 'Income details updated successfully');
    }

    public function destroy($id)
    {
        Income::findOrFail($id)->delete();
        return back()->with('message', 'Income details deleted successfully');
    }
}
