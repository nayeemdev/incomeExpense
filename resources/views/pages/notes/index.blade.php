@extends('layouts.master')

@section('content')
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('index') }}">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Notes</li>
        </ol>

        <div class="row">
            @foreach($notes as $note)
                <div class="col-xl-4 col-sm-6 mb-3">
                    <div class="card text-white bg-info o-hidden h-100">
                        <div class="card-header">
                            <span class="float-left">12-08-12 8:05 PM</span>
                            <span class="float-right">
                                <span style="cursor:pointer;" class="mr-2"><i class="fa fa-edit"></i></span>
                                <span style="cursor:pointer;"><i class="fa fa-trash"></i></span>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="card-body-icon mt-5">
                                <i class="fas fa-fw fa-sticky-note"></i>
                            </div>
                            <div>{{ $note->note_title }}</div>
                            <div>{{ $note->note_amount }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="col-xl-12 col-sm-12">
                {{ $notes->links() }}
            </div>
        </div>
@endsection