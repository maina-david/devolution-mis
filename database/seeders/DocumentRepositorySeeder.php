<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentRepositorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $creator = User::query()->role(UserRole::DevolutionAdmin->value)->first()
            ?? User::query()->role(UserRole::PlatformAdmin->value)->first();
        if ($creator === null) {
            return;
        }

        foreach (County::query()->orderBy('code')->get() as $county) {
            $folders = collect([
                'plans' => 'Plans and budgets',
                'assessment' => 'Assessments and audit',
                'participation' => 'Public participation and legislation',
                'delivery' => 'Programme delivery',
            ])->mapWithKeys(function (string $name, string $key) use ($county, $creator): array {
                $root = DocumentFolder::query()->firstOrCreate(
                    ['parent_id' => null, 'county_id' => $county->id, 'name' => $name],
                    ['created_by' => $creator->id],
                );
                $current = DocumentFolder::query()->firstOrCreate(
                    ['parent_id' => $root->id, 'county_id' => $county->id, 'name' => 'Current cycle'],
                    ['created_by' => $creator->id],
                );

                return [$key => $current];
            });

            AssessmentDocument::query()
                ->where('county_id', $county->id)
                ->whereNull('folder_id')
                ->each(function (AssessmentDocument $document) use ($folders): void {
                    $classification = $this->classification($document);
                    $document->update(['folder_id' => $folders->get($classification)?->id]);
                });
        }

        foreach (['National policy and standards', 'Programme governance', 'Consolidated county submissions', 'Independent verification'] as $name) {
            DocumentFolder::query()->firstOrCreate(
                ['parent_id' => null, 'county_id' => null, 'name' => $name],
                ['created_by' => $creator->id],
            );
        }
    }

    private function classification(AssessmentDocument $document): string
    {
        $searchable = Str::lower($document->title.' '.$document->category.' '.$document->description);

        if (Str::contains($searchable, ['cidp', 'adp', 'budget', 'plan'])) {
            return 'plans';
        }

        if (Str::contains($searchable, ['participation', 'citizen', 'legislation', 'act', 'minutes'])) {
            return 'participation';
        }

        if (Str::contains($searchable, ['audit', 'assessment', 'acpa', 'verification'])) {
            return 'assessment';
        }

        return 'delivery';
    }
}
