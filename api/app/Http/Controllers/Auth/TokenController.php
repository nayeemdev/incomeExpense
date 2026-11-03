<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TokenAbility;
use App\Http\Controllers\Controller;
use App\Http\Requests\IssueTokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function store(IssueTokenRequest $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->lower())->first();

        if (! $user || ! Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $this->verifyTwoFactor($request, $user, $twoFactor);
        }

        $token = $user->createToken($request->string('device_name')->value(), [TokenAbility::Full->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    private function verifyTwoFactor(IssueTokenRequest $request, User $user, TwoFactorAuthenticationProvider $twoFactor): void
    {
        $code = $request->string('code')->value();
        $recoveryCode = $request->string('recovery_code')->value();

        if ($code === '' && $recoveryCode === '') {
            throw ValidationException::withMessages(['code' => __('A two-factor authentication code is required.')]);
        }

        if ($code !== '' && $twoFactor->verify(decrypt((string) $user->two_factor_secret), $code)) {
            return;
        }

        if ($recoveryCode !== '' && in_array($recoveryCode, $user->recoveryCodes(), true)) {
            $user->replaceRecoveryCode($recoveryCode);

            return;
        }

        throw ValidationException::withMessages(['code' => __('The provided two-factor authentication code was invalid.')]);
    }
}
