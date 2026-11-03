<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SavedSearchController extends Controller
{
    public function index(Ledger $ledger, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => DB::table('saved_searches')
            ->where('ledger_id', $ledger->id)
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'query'])]);
    }

    public function store(Request $request, Ledger $ledger, #[CurrentUser] User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'query' => ['required', 'string', 'max:1000'],
        ]);

        $id = DB::table('saved_searches')->insertGetId([
            ...$validated,
            'ledger_id' => $ledger->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id, ...$validated]], Response::HTTP_CREATED);
    }

    public function destroy(Ledger $ledger, #[CurrentUser] User $user, int $savedSearch): Response
    {
        abort_unless(
            DB::table('saved_searches')->where('ledger_id', $ledger->id)->where('user_id', $user->id)->where('id', $savedSearch)->delete() > 0,
            404,
        );

        return response()->noContent();
    }
}
