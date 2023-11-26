@extends('layouts.master')

@section('content')
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('index') }}">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">
                <a href="{{ route('expense.index') }}">Expense</a>
            </li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">×</span></button>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </div>
        @endif
        <div class="row">
            <div class="col-xl-8 offset-2">
                <div class="card mx-auto mt-5">
                    <div class="card-header">Update Expense</div>
                    <div class="card-body">
                        <form action="{{ route('expenses.update') }}" method="POST">
                            @csrf
                            <input type="hidden" name="expense_id" value="{{ $expense->id }}">
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="text" id="expense_title" class="form-control" placeholder="Email address" required="required" autofocus="autofocus" name="expense_title" value="{{ $expense->expense_title }}">
                                    <label for="expense_title">Expense Description</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="number" step="any" min="0.01" id="expense_amount" class="form-control" placeholder="Password" required="required" name="expense_amount" value="{{ $expense->expense_amount }}">
                                    <label for="expense_amount">Expense Amount</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="date" id="expense_date" class="form-control" placeholder="Password" required="required" name="expense_date" value="{{ $expense->expense_date }}">
                                    <label for="expense_date">Expense Date</label>
                                </div>
                            </div>
                            <div class="float-right">
                                <a href="{{ route('expense.index') }}" class="btn btn-success">Back</a>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
