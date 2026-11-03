<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function perPage(Request $request): int
    {
        $default = config()->integer('ledgerfly.pagination.per_page');

        return max(1, min($request->integer('per_page', $default), config()->integer('ledgerfly.pagination.max_per_page')));
    }
}
