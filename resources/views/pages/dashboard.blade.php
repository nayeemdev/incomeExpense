@extends('layouts.master')

@section('content')

@if (session('status'))
    <div class="alert alert-success" role="alert">
        {{ session('status') }}
    </div>
@endif

<div class="container-fluid">

    <!-- Breadcrumbs-->
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="#">Dashboard</a>
        </li>
        <li class="breadcrumb-item active">Overview</li>
    </ol>

    <div class="row">
        <div class="col-xl-6 offset-xl-3 col-sm-12 mb-3">
            <ul class="list-group">
                <li class="list-group-item bg-info text-center text-white">
                    <span>This Month Cost</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Total Income
                    <span class="badge badge-primary badge-pill incomeValue">{{ $incomes}}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Total Expense
                    <span class="badge badge-danger badge-pill expenseValue">{{ $expenses }}</span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Balance
                    <span class="badge badge-primary badge-pill">{{ $balance }}</span>
                </li>
            </ul>
        </div>

    </div>
    <!-- Icon Cards-->
    <div class="row">

        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-primary o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-table"></i>
                    </div>
                    <div class="mr-5">Total Summary</div>
                </div>
                <a class="nav-link text-white text-center card-footer clearfix small z-1" href="{{ route('notes.index') }}"  class="card-footer text-white clearfix small z-1" href="#">
                    <span class="float-left">View All Summary</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-success o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-dollar-sign"></i>
                    </div>
                    <div class="mr-5">{{ App\Models\Income::where('user_id', Auth::user()->id)->count() }} Income</div>
                </div>
                <a class="nav-link text-white text-center card-footer clearfix small z-1" href="{{ route('incomes.index') }}"  class="card-footer text-white clearfix small z-1" href="#">
                    <span class="float-left">View All</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-danger o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-money-bill"></i>
                    </div>
                    <div class="mr-5">{{ App\Models\Expense::where('user_id', Auth::user()->id)->count() }} Expenses</div>
                </div>
                <a class="nav-link text-white text-center card-footer clearfix small z-1" href="{{ route('expense.index') }}" href="#">
                    <span class="float-left">View All</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="card text-white bg-info o-hidden h-100">
                <div class="card-body">
                    <div class="card-body-icon">
                        <i class="fas fa-fw fa-sticky-note"></i>
                    </div>
                    <div>{{ App\Models\Note::where('user_id', Auth::user()->id)->count() }} Note</div>
                </div>
                <a class="nav-link text-white text-center card-footer clearfix small z-1" href="{{ route('notes.index') }}"  class="card-footer text-white clearfix small z-1" href="#">
                    <span class="float-left">View All</span>
                    <span class="float-right">
                        <i class="fas fa-angle-right"></i>
                    </span>
                </a>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i>
                Income Vs Expense <small class="badge badge-info">(This Month Data)</small></div>
            <div class="card-body">
                <canvas id="incomeExpenseChart" width="100%" height="30"></canvas>
            </div>
            <div class="card-footer small text-muted">Updated yesterday at 11:59 PM</div>
            </div>
        </div>
        </div>
    </div>
@endsection

@push('js')
    <script src="{{ asset('dashboard/vendor/chart/chart.min.js') }}"></script>
    <script>
        //Income expense Pie Chart
        var ctx = document.getElementById("incomeExpenseChart");
        var income = $(".incomeValue").html();
        var expense = $(".expenseValue").html();
        var incomeExpenseChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ["Income", "Expense"],
                datasets: [{
                data: [income, expense],
                backgroundColor: ['#007bff', '#dc3545'],
                }],
            },
        });
    </script>
@endpush
