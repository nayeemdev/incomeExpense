<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PublicView extends Controller
{
    /**
     * Show the profile for the given user.
     *
     * @param  int  $id
     * @return View
     */
    public function overview()
    {
        return view('pages.overview');
    }
}
