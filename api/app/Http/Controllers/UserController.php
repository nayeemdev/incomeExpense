<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class UserController extends Controller
{
    public function show(#[CurrentUser] User $user): UserResource
    {
        return new UserResource($user);
    }

    public function updatePreferences(UpdatePreferencesRequest $request, #[CurrentUser] User $user): UserResource
    {
        $user->update([
            'preferences' => array_merge($user->preferences ?? [], $request->validated()),
        ]);

        return new UserResource($user);
    }
}
