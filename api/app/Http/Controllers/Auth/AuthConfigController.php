<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AuthConfigController extends Controller
{
    /**
     * Public facts the sign-in screens need before anyone is signed in.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'registration_open' => CreateNewUser::isOpenFor(null),
        ]]);
    }
}
