<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            $ledger->categories()
                ->withCount('transactions')
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->orderBy('name')
                ->paginate($this->perPage($request)),
        );
    }

    public function styles(): JsonResponse
    {
        return response()->json(['data' => [
            'icons' => config()->array('ledgerfly.categories.icons'),
            'colors' => config()->array('ledgerfly.categories.colors'),
        ]]);
    }

    public function store(CategoryRequest $request, Ledger $ledger): CategoryResource
    {
        return new CategoryResource($ledger->categories()->create($request->validated()));
    }

    public function show(Ledger $ledger, Category $category): CategoryResource
    {
        return new CategoryResource($category->loadCount('transactions'));
    }

    public function update(CategoryRequest $request, Ledger $ledger, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Ledger $ledger, Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }
}
