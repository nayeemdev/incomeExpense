<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Http\Resources\TagResource;
use App\Models\Ledger;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TagController extends Controller
{
    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        return TagResource::collection(
            $ledger->tags()
                ->withCount('transactions')
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->orderBy('name')
                ->paginate($this->perPage($request)),
        );
    }

    public function store(TagRequest $request, Ledger $ledger): TagResource
    {
        return new TagResource($ledger->tags()->create($request->validated()));
    }

    public function show(Ledger $ledger, Tag $tag): TagResource
    {
        return new TagResource($tag->loadCount('transactions'));
    }

    public function update(TagRequest $request, Ledger $ledger, Tag $tag): TagResource
    {
        $tag->update($request->validated());

        return new TagResource($tag);
    }

    public function destroy(Ledger $ledger, Tag $tag): Response
    {
        $tag->delete();

        return response()->noContent();
    }
}
