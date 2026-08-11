<?php

namespace App\Services;

use App\Models\KnowledgeItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KnowledgeSearch
{
    /**
     * @param  Builder<KnowledgeItem>  $query
     * @return Builder<KnowledgeItem>
     */
    public function apply(Builder $query, string $search): Builder
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $query->where(fn (Builder $searchQuery) => $searchQuery->where('reference', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")->orWhere('summary', 'like', "%{$search}%")->orWhere('content_body', 'like', "%{$search}%"))->latest();
        }

        $vector = "setweight(to_tsvector('simple', coalesce(title, '')), 'A') || setweight(to_tsvector('simple', coalesce(summary, '')), 'B') || setweight(to_tsvector('simple', coalesce(content_body, '')), 'C') || setweight(to_tsvector('simple', coalesce(reference, '') || ' ' || coalesce(tags::text, '')), 'D')";
        $query->select('knowledge_items.*')->selectRaw("ts_rank_cd({$vector}, websearch_to_tsquery('simple', ?)) as search_rank", [$search])
            ->where(function (Builder $searchQuery) use ($search, $vector): void {
                $searchQuery->whereRaw("{$vector} @@ websearch_to_tsquery('simple', ?)", [$search])
                    ->orWhereHas('document.currentVersion.extraction', fn (Builder $extraction) => $extraction->where('status', 'completed')->whereRaw("to_tsvector('simple', coalesce(extracted_text, '')) @@ websearch_to_tsquery('simple', ?)", [$search]));
            })->orderByDesc('search_rank')->latest('knowledge_items.created_at');

        return $query;
    }
}
