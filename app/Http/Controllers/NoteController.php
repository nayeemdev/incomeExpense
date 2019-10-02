<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Note;
use Auth;

class NoteController extends Controller
{
	public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $data['notes'] = Note::where('user_id', Auth::User()->id)->paginate(15);
        return view('pages.notes.index', $data);
    }
}
