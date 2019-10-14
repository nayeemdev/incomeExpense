@extends('layouts.master')

@section('content')
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('index') }}">Dashboard</a>
            </li>
            <li class="breadcrumb-item active">
                <a href="{{ route('notes.index') }}">Note</a>
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
                    <div class="card-header">Update Note</div>
                    <div class="card-body">
                        <form action="{{ route('notes.update') }}" method="POST">
                            @csrf
                            <input type="hidden" name="note_id" value="{{ $note->id }}">
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="text" id="note_title" class="form-control" placeholder="Email address" required="required" autofocus="autofocus" name="note_title" value="{{ $note->note_title }}">
                                    <label for="note_title">Note Description</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="number" step="any" min="0.01" id="note_amount" class="form-control" placeholder="Password" required="required" name="note_amount" value="{{ $note->note_amount }}">
                                    <label for="note_amount">Note Amount</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="form-label-group">
                                    <input type="date" id="note_date" class="form-control" placeholder="Password" required="required" name="note_date" value="{{ $note->note_date }}">
                                    <label for="note_date">Note Date</label>
                                </div>
                            </div>
                            <div class="float-right">
                                <a href="{{ route('notes.index') }}" class="btn btn-success">Back</a>
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
