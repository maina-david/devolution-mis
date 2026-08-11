<?php

namespace Database\Seeders;

use App\Actions\CreateCitizenCase;
use App\Actions\TriageCitizenCase;
use App\Enums\UserRole;
use App\Models\CitizenCase;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Seeder;

class CitizenCaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateCitizenCase $createCase, TriageCitizenCase $triageCase): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $administrator = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        $mombasaOfficer = User::query()->where('email', 'county.official@idmis.test')->first();
        $nairobiAdministrator = User::query()->where('email', 'county.admin@idmis.test')->first();
        $mombasa = County::query()->where('name', 'Mombasa')->first();
        $nairobi = County::query()->where('name', 'Nairobi')->first();

        if (! $administrator || ! $mombasaOfficer || ! $nairobiAdministrator || ! $mombasa || ! $nairobi) {
            return;
        }

        $this->call(CitizenCaseWorkflowSeeder::class);
        $this->createBaselineCase($createCase, $triageCase, $administrator, $mombasaOfficer, $mombasa, 'feedback', 'suggestion', 'Improve public participation notices', 'Publish meeting notices in accessible formats and local languages.', 'medium');
        $this->createBaselineCase($createCase, $triageCase, $administrator, $nairobiAdministrator, $nairobi, 'grievance', 'grievance', 'Delayed response to a county service request', 'A previously lodged service request has not received a response within the stated service period.', 'high');
    }

    private function createBaselineCase(CreateCitizenCase $createCase, TriageCitizenCase $triageCase, User $administrator, User $assignee, County $county, string $caseType, string $category, string $subject, string $description, string $priority): void
    {
        if (CitizenCase::query()->where('subject', $subject)->where('county_id', $county->id)->exists()) {
            return;
        }

        $result = $createCase->handle([
            'case_type' => $caseType,
            'category' => $category,
            'channel' => 'web',
            'county_id' => $county->id,
            'sector_id' => null,
            'subject' => $subject,
            'description' => $description,
            'citizen_name' => null,
            'citizen_email' => null,
            'citizen_phone' => null,
            'is_anonymous' => true,
            'preferred_contact' => 'none',
            'accessibility_needs' => null,
            'consent_given' => true,
            'privacy_notice_version' => '2026-08',
        ]);

        $triageCase->handle($result['case'], $administrator, [
            'assigned_to' => $assignee->id,
            'assigned_organization_id' => null,
            'sector_id' => null,
            'priority' => $priority,
            'is_sensitive' => false,
            'triage_note' => 'Seeded representative case for local role and county-scope validation.',
        ]);
    }
}
