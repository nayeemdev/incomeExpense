@extends('layouts.master')

@section('content')
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('index') }}">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Expense</li>
        </ol>
        @if (Session::has('message'))
        <div class="alert alert-success alert-dismissible fade show rounded" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">×</span></button> <i class="fa fa-info mx-2"></i>
            <strong>{!! session('message') !!}</strong>
        </div>
        @endif
        <div class="row">
        	<div class="col-xl-6 offset-xl-3 col-sm-12 mb-3">
        		<ul class="list-group">
				  <li class="list-group-item d-flex justify-content-between align-items-center">
				    <a href="{{ route('expense.create') }}" class="badge badge-primary p-2 mx-auto">Add New Expense</a>
				  </li>
				  <li class="list-group-item d-flex justify-content-between align-items-center">
				    Total Expense
                      <span class="badge badge-danger badge-pill">{{ $totalExpenses }}</span>
				  </li>
				</ul>
        	</div>
        </div>
        <div class="row">
            @foreach($expenses as $expense)
                <div class="col-xl-4 col-sm-6 mb-3">
                    <div class="card text-white bg-danger o-hidden h-100">
                        <div class="card-header">
                            <span class="float-left text-dark">{{ $expense->expense_date }}</span>
                            <span class="btn-group-sm float-right">
                                <a href="{{ route('expenses.edit',$expense->id) }}" class="btn btn-sm btn-success"><i class="fa fa-edit"></i></a>
                                <a href="{{ route('expenses.delete',$expense->id) }}" class="btn btn-sm btn-success"><i class="fa fa-trash"></i></a>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="card-body-icon mt-5">
                                <i class="fas fa-fw fa-money-bill"></i>
                            </div>
                            <div>{{ $expense->expense_title }}</div>
                            <div class="font-weight-bold"><span style="font-weight:900;">৳ </span> {{ $expense->expense_amount }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="col-xl-12 col-sm-12">
                {{ $expenses->links() }}
            </div>
        </div>
    </div>
@endsection
