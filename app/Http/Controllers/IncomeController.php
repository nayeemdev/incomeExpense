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
    public function index()
    {
        $data['incomes'] = Income::where('user_id', Auth::user()->id)->latest()->paginate(12);
        $data['totalIncomes'] = Income::where('user_id', Auth::user()->id)->sum('income_amount');

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
