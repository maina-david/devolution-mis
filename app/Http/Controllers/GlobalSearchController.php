<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalSearchRequest;
use App\Models\User;
use App\Services\GlobalSearch;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(GlobalSearchRequest $request, GlobalSearch $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'results' => $search->for($user, $request->string('q')->trim()->toString()),
        ]);
    }
}
