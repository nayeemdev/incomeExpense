<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SystemSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => SystemSettings::forDisplay()]);
    }

    public function update(Request $request): JsonResponse
    {
        $rules = [];
        foreach (SystemSettings::definitions() as $key => $definition) {
            $rules[$key] = ['sometimes', ...$definition['rules']];
        }

        SystemSettings::save($request->validate($rules));

        return $this->show();
    }

    public function testMail(#[CurrentUser] User $user): JsonResponse
    {
        try {
            Mail::raw(__('Mail from LedgerFly is working.'), fn ($message) => $message->to($user->email)->subject(__('LedgerFly test email')));
        } catch (Throwable $exception) {
            return response()->json(['message' => __('Sending failed: :error', ['error' => $exception->getMessage()])], 422);
        }

        return response()->json(['data' => ['sent_to' => $user->email]]);
    }
}
