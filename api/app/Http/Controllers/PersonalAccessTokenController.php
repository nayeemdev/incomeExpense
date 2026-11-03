<?php

namespace App\Http\Controllers;

use App\Enums\TokenAbility;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Notifications\TokenCreatedNotification;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PersonalAccessTokenController extends Controller
{
    public function index(#[CurrentUser] User $user): AnonymousResourceCollection
    {
        return PersonalAccessTokenResource::collection(
            $user->tokens()->latest()->get(),
        );
    }

    public function store(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::enum(TokenAbility::class)],
        ]);

        $token = $user->createToken($validated['name'], $validated['abilities']);
        $user->notify(new TokenCreatedNotification($validated['name']));

        return response()->json([
            'token' => $token->plainTextToken,
            'data' => new PersonalAccessTokenResource($token->accessToken),
        ], Response::HTTP_CREATED);
    }

    public function destroy(#[CurrentUser] User $user, string $token): Response
    {
        $user->tokens()->whereKey($token)->firstOrFail()->delete();

        return response()->noContent();
    }
}
