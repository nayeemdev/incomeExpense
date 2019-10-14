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
        $data['notes'] = Note::where('user_id', Auth::User()->id)->latest()->paginate(12);
        return view('pages.notes.index', $data);
    }

    public function create()
    {
        return view('pages.notes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'note_title' => 'required',
            'note_amount' => 'required',
            'note_date'=> 'required'
        ]);

        $notes = new Note();
        $notes->note_title = $request->note_title;
        $notes->note_amount = $request->note_amount;
        $notes->note_date = $request->note_date;
        $notes->user_id = Auth::user()->id;
        $notes->save();

        return redirect('/notes')->with('message', 'New note Added');
    }

    public function edit($id)
    {
        $data['note'] = note::findOrFail($id);
        return view('pages.notes.edit', $data);
    }

    public function update(Request $request)
    {
        $request->validate([
            'note_title' => 'required',
            'note_amount' => 'required',
            'note_date'=> 'required'
        ]);

        $note = Note::findOrFail($request->note_id);
        $note->note_title = $request->note_title;
        $note->note_amount = $request->note_amount;
        $note->note_date = $request->note_date;
        $note->update();

        return redirect('/notes')->with('message', 'note details updated successfully');
    }

    public function destroy($id)
    {
        Note::findOrFail($id)->delete();
        return back()->with('message', 'Note details deleted successfully');
    }
}
