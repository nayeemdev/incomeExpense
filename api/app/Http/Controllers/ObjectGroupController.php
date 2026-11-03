<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\ObjectGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ObjectGroupController extends Controller
{
    public function index(Ledger $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->objectGroups()->orderBy('order')->orderBy('title')->get(['id', 'title', 'order'])]);
    }

    public function update(Request $request, Ledger $ledger, ObjectGroup $objectGroup): JsonResponse
    {
        $objectGroup->update($request->validate([
            'title' => ['sometimes', 'string', 'max:255', Rule::unique('object_groups')->where('ledger_id', $ledger->id)->ignore($objectGroup->id)],
            'order' => ['sometimes', 'integer', 'min:0'],
        ]));

        return response()->json(['data' => $objectGroup->only('id', 'title', 'order')]);
    }

    public function destroy(Ledger $ledger, ObjectGroup $objectGroup): Response
    {
        $objectGroup->delete();

        return response()->noContent();
    }
}
