@extends('layouts.master')

@section('content')
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('index') }}">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">Notes</li>
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
				    <a href="{{ route('notes.create') }}" class="badge badge-success p-2 mx-auto" >Add New Note</a>
				  </li>
				</ul>
        	</div>
        </div>

        <div class="row">
            @foreach($notes as $note)
                <div class="col-xl-4 col-sm-6 mb-3">
                    <div class="card text-white bg-info o-hidden h-100">
                        <div class="card-header">
                            <span class="float-left">{{ $note->note_date }}</span>
                            <span class="btn-group-sm float-right d-flex">
                                <a href="{{ route('notes.edit',$note) }}" class="btn btn-sm btn-success mr-2"><i class="fa fa-edit"></i></a>
                                <form action="{{ route('notes.destroy', $note->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                                  </form>
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
