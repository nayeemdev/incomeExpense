<?php

namespace App\Http\Controllers;

use App\Http\Resources\RuleResource;
use App\Models\Ledger;
use App\Models\RuleGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RuleGroupController extends Controller
{
    public function index(Ledger $ledger): JsonResponse
    {
        $groups = $ledger->ruleGroups()
            ->with('rules.triggers', 'rules.actions')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $groups->map(fn (RuleGroup $group) => $this->present($group))]);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $group = $ledger->ruleGroups()->create($this->validated($request, true));

        return response()->json(['data' => $this->present($group->load('rules'))], Response::HTTP_CREATED);
    }

    public function update(Request $request, Ledger $ledger, RuleGroup $ruleGroup): JsonResponse
    {
        $ruleGroup->update($this->validated($request, false));

        return response()->json(['data' => $this->present($ruleGroup->load('rules.triggers', 'rules.actions'))]);
    }

    public function destroy(Ledger $ledger, RuleGroup $ruleGroup): Response
    {
        $ruleGroup->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65000'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'stop_processing' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(RuleGroup $group): array
    {
        return [
            'id' => $group->id,
            'title' => $group->title,
            'description' => $group->description,
            'order' => $group->order,
            'active' => $group->active,
            'stop_processing' => $group->stop_processing,
            'rules' => RuleResource::collection($group->rules),
        ];
    }
}
