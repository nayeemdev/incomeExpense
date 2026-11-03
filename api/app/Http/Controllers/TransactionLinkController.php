<?php

namespace App\Http\Controllers;

use App\Models\Ledger;
use App\Models\LinkType;
use App\Models\TransactionGroup;
use App\Models\TransactionLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionLinkController extends Controller
{
    public function linkTypes(Ledger $ledger): JsonResponse
    {
        return response()->json(['data' => LinkType::availableTo($ledger)->orderBy('name')->get()->map(fn (LinkType $type) => [
            'id' => $type->id,
            'name' => $type->name,
            'outward' => $type->outward,
            'inward' => $type->inward,
            'custom' => $type->getAttribute('ledger_id') !== null,
        ])]);
    }

    public function storeLinkType(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('link_types')->where('ledger_id', $ledger->id)],
            'outward' => ['required', 'string', 'max:255'],
            'inward' => ['required', 'string', 'max:255'],
        ]);

        $type = new LinkType($validated);
        $type->setAttribute('ledger_id', $ledger->id);
        $type->save();

        return response()->json(['data' => ['id' => $type->id, ...$validated, 'custom' => true]], Response::HTTP_CREATED);
    }

    public function destroyLinkType(Ledger $ledger, string $linkType): Response
    {
        LinkType::query()->where('ledger_id', $ledger->id)->findOrFail($linkType)->delete();

        return response()->noContent();
    }

    public function index(Ledger $ledger, TransactionGroup $transactionGroup): JsonResponse
    {
        $links = $ledger->transactionLinks()
            ->with('linkType', 'outward.transactions', 'inward.transactions')
            ->where(fn ($query) => $query->where('outward_group_id', $transactionGroup->id)->orWhere('inward_group_id', $transactionGroup->id))
            ->get();

        return response()->json(['data' => $links->map(fn (TransactionLink $link) => $this->present($link, $transactionGroup))]);
    }

    public function store(Request $request, Ledger $ledger, TransactionGroup $transactionGroup): JsonResponse
    {
        $validated = $request->validate([
            'link_type_id' => ['required', 'ulid'],
            'group_id' => ['required', 'ulid', Rule::notIn([$transactionGroup->id])],
            'direction' => ['sometimes', Rule::in(['outward', 'inward'])],
            'notes' => ['nullable', 'string', 'max:65000'],
        ]);

        $type = LinkType::availableTo($ledger)->find((string) $validated['link_type_id']) ?? throw ValidationException::withMessages(['link_type_id' => __('Unknown link type.')]);
        $other = $ledger->transactionGroups()->findOrFail((string) $validated['group_id']);
        $outward = ($validated['direction'] ?? 'outward') === 'outward';

        $link = $ledger->transactionLinks()->firstOrCreate([
            'link_type_id' => $type->id,
            'outward_group_id' => $outward ? $transactionGroup->id : $other->id,
            'inward_group_id' => $outward ? $other->id : $transactionGroup->id,
        ], ['notes' => $validated['notes'] ?? null]);

        return response()->json(['data' => $this->present($link->load('linkType', 'outward.transactions', 'inward.transactions'), $transactionGroup)], Response::HTTP_CREATED);
    }

    public function destroy(Ledger $ledger, TransactionLink $transactionLink): Response
    {
        $transactionLink->delete();

        return response()->noContent();
    }

    /**
     * Describes the link from the point of view of $group, e.g. "refunds Groceries".
     *
     * @return array<string, mixed>
     */
    private function present(TransactionLink $link, TransactionGroup $group): array
    {
        $outward = $link->getAttribute('outward_group_id') === $group->id;
        $other = $outward ? $link->inward : $link->outward;

        return [
            'id' => $link->id,
            'link_type_id' => $link->linkType->id,
            'label' => $outward ? $link->linkType->outward : $link->linkType->inward,
            'group' => [
                'id' => $other->id,
                'title' => $other->title ?? $other->transactions->first()?->description,
                'date' => $other->transactions->first()?->date->toDateString(),
            ],
            'notes' => $link->getAttribute('notes'),
        ];
    }
}
