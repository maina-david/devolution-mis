<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckUniqueValueRequest;
use App\Models\County;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class UniqueValueController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CheckUniqueValueRequest $request): JsonResponse
    {
        /** @var array{resource: 'counties'|'organizations'|'sectors'|'programmes', field: 'code'|'name', value: string, exclude_id?: string|null} $attributes */
        $attributes = $request->validated();
        /** @var array<string, class-string<Model>> $models */
        $models = [
            'counties' => County::class,
            'organizations' => Organization::class,
            'sectors' => Sector::class,
            'programmes' => Programme::class,
        ];
        $available = ! $models[$attributes['resource']]::query()
            ->when($attributes['exclude_id'] ?? null, fn ($query, string $id) => $query->whereKeyNot($id))
            ->where($attributes['field'], $attributes['value'])
            ->exists();

        return response()->json([
            'available' => $available,
            'message' => $available
                ? ucfirst($attributes['field']).' is available.'
                : ucfirst($attributes['field']).' is already in use.',
        ]);
    }
}
