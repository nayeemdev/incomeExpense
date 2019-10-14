@extends('layouts.master')

@section('content')
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('index') }}">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">
                <a href="{{ route('incomes.index') }}">Income</a>
            </li>
            <li class="breadcrumb-item active">Insert</li>
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
                    <div class="card-header">Insert New Income</div>
                    <div class="card-body">
                        <form action="{{ route('incomes.store') }}" method="POST">
                            @csrf
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="text" id="income_title" class="form-control" placeholder="Email address" required="required" autofocus="autofocus" name="income_title">
                                    <label for="income_title">Income Description</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="number" step="any" min="0.01" id="income_amount" class="form-control" placeholder="Password" required="required" name="income_amount">
                                    <label for="income_amount">Income Amount</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="date" id="income_date" class="form-control" placeholder="Income Date" required="required" name="income_date" value="{{ date('Y-m-d') }}">
                                    <label for="income_date">Income Date</label>
                                </div>
                            </div>
                            <div class="float-right">
                                <a href="{{ route('incomes.index') }}" class="btn btn-success">Back</a>
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
