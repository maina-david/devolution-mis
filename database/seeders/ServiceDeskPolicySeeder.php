<?php

namespace Database\Seeders;

use App\Actions\CreateServiceDeskPolicy;
use App\Actions\PublishServiceDeskPolicy;
use App\Enums\UserRole;
use App\Models\BusinessCalendar;
use App\Models\ServiceDeskPolicy;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceDeskPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateServiceDeskPolicy $createPolicy, PublishServiceDeskPolicy $publishPolicy): void
    {
        if (ServiceDeskPolicy::query()->where('code', 'IDMIS-SUPPORT')->where('status', 'published')->exists()) {
            return;
        }

        $author = User::role(UserRole::DevolutionAdmin->value)->orderBy('name')->firstOrFail();
        $publisher = User::role(UserRole::PlatformAdmin->value)->orderBy('name')->firstOrFail();
        $calendar = BusinessCalendar::query()->where('status', 'published')->whereNotNull('checksum')->latest('version')->firstOrFail();
        $policy = ServiceDeskPolicy::query()->where('code', 'IDMIS-SUPPORT')->where('status', 'draft')->first();
        if ($policy === null) {
            $policy = $createPolicy->handle($author, [
                'code' => 'IDMIS-SUPPORT',
                'name' => 'IDMIS operational support policy',
                'description' => 'Provisional engineering service catalogue for authorized IDMIS access, data, integration, records, training and operational incident support.',
                'business_calendar_id' => $calendar->id,
                'categories' => [
                    ['code' => 'access', 'name' => 'Access and identity'],
                    ['code' => 'incident', 'name' => 'Service incident'],
                    ['code' => 'service_request', 'name' => 'Service request'],
                    ['code' => 'data_quality', 'name' => 'Data quality'],
                    ['code' => 'integration', 'name' => 'Integration'],
                    ['code' => 'training', 'name' => 'Training and adoption'],
                    ['code' => 'document', 'name' => 'Documents and OCR'],
                    ['code' => 'other', 'name' => 'Other'],
                ],
                'channels' => ['web', 'email', 'phone', 'walk_in', 'training'],
                'priority_targets' => [
                    'critical' => ['first_response' => 1, 'resolution' => 4, 'reminder' => 0.5],
                    'high' => ['first_response' => 4, 'resolution' => 16, 'reminder' => 2],
                    'medium' => ['first_response' => 8, 'resolution' => 40, 'reminder' => 4],
                    'low' => ['first_response' => 16, 'resolution' => 80, 'reminder' => 8],
                ],
                'escalation_rules' => collect(['critical', 'high', 'medium', 'low'])->flatMap(fn (string $priority): array => [
                    ['priority' => $priority, 'stage' => 'first_response', 'tier' => $priority === 'critical' ? 3 : 2],
                    ['priority' => $priority, 'stage' => 'resolution', 'tier' => 3],
                ])->values()->all(),
                'effective_from' => now()->startOfDay(),
                'effective_to' => $calendar->effective_to,
                'roster' => [
                    ['user_id' => $author->id, 'county_id' => null, 'tier' => 1, 'duty_role' => 'responder', 'is_primary' => true, 'starts_at' => now()->startOfDay(), 'ends_at' => null],
                    ['user_id' => $publisher->id, 'county_id' => null, 'tier' => 3, 'duty_role' => 'manager', 'is_primary' => true, 'starts_at' => now()->startOfDay(), 'ends_at' => null],
                ],
            ]);
        }

        $publishPolicy->handle($policy, $publisher, ['authority_status' => 'provisional', 'approval_reference' => null]);
    }
}
