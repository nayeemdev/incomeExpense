<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Note;

class NoteController extends Controller
{
    public function index()
    {
        $data['notes'] = Note::paginate(15);
        return view('pages.notes.index', $data);
    }
}
