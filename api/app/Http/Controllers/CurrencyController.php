<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CurrencyResource::collection(Currency::query()->orderBy('code')->get());
    }
}
