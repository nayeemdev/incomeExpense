@extends('layouts.master')

@section('content')
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('index') }}">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">All Summary</li>
        </ol>
        <div class="row">
        	<div class="col-xl-6 offset-xl-3 col-sm-12 mb-3">
        		<ul class="list-group">
				  <li class="list-group-item d-flex bg-info text-white justify-content-center align-items-center">
				    All Data
				  </li>
				  <li class="list-group-item d-flex justify-content-between align-items-center">
				    Total Income
				    <span class="badge badge-primary badge-pill">{{ $total_income }}</span>
				  </li>
				  <li class="list-group-item d-flex justify-content-between align-items-center">
				    Total Expense
				    <span class="badge badge-danger badge-pill">{{ $total_expense }}</span>
				  </li>
				  <li class="list-group-item d-flex justify-content-between align-items-center">
				    Balance
				    <span class="badge badge-primary badge-pill">{{ $balance }}</span>
				  </li>
				</ul>
        	</div>
        </div>
        <div class="row">
            @foreach($results as $result)
                <div class="col-xl-4 col-sm-6 mb-3">
                    <div class="card text-white {{($result['type'] == 'income')? 'bg-info':'bg-danger'}} o-hidden h-100">
                        <div class="card-header">
                            <span class="float-left text-dark">{{($result['type'] == 'income')? $result['created_at']:$result['created_at']}}</span>
                        </div>
                        <div class="card-body">
                            <div class="card-body-icon mt-5">
                                <i class="fas fa-fw {{($result['type'] == 'income')? 'fa-dollar-sign':'fa-money-bill'}} "></i>
                            </div>
                            <div>{{($result['type'] == 'income')? $result['income_title']:$result['expense_title']}}</div>
                            <div class="font-weight-bold text-dark">{{($result['type'] == 'income')? '৳ '.$result['income_amount']: '৳ '.$result['expense_amount']}}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
