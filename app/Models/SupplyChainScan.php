<?php

namespace App\Models;

use Database\Factories\SupplyChainScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $environment
 * @property string|null $source_revision
 * @property string $source_state
 * @property string $composer_lock_checksum
 * @property string $javascript_lock_checksum
 * @property string $javascript_lockfile
 * @property int $composer_component_count
 * @property int $javascript_component_count
 * @property int $composer_advisory_count
 * @property int $npm_info_count
 * @property int $npm_low_count
 * @property int $npm_moderate_count
 * @property int $npm_high_count
 * @property int $npm_critical_count
 * @property list<string> $finding_codes
 * @property array<string, string> $tool_versions
 * @property string $sbom_format
 * @property string $sbom_spec_version
 * @property string $outcome
 * @property string|null $path
 * @property string $disk
 * @property string $mime_type
 * @property int|null $size_bytes
 * @property string|null $artifact_checksum
 * @property string|null $failure_category
 * @property string|null $initiated_by
 * @property string $initiated_by_name
 * @property string $evidence_checksum
 * @property Carbon $started_at
 * @property Carbon $completed_at
 */
#[Fillable(['id', 'environment', 'source_revision', 'source_state', 'composer_lock_checksum', 'javascript_lock_checksum', 'javascript_lockfile', 'composer_component_count', 'javascript_component_count', 'composer_advisory_count', 'npm_info_count', 'npm_low_count', 'npm_moderate_count', 'npm_high_count', 'npm_critical_count', 'finding_codes', 'tool_versions', 'sbom_format', 'sbom_spec_version', 'disk', 'path', 'mime_type', 'size_bytes', 'artifact_checksum', 'outcome', 'failure_category', 'initiated_by', 'initiated_by_name', 'started_at', 'completed_at', 'evidence_checksum'])]
class SupplyChainScan extends Model
{
    /** @use HasFactory<SupplyChainScanFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['composer_component_count' => 'integer', 'javascript_component_count' => 'integer', 'composer_advisory_count' => 'integer', 'npm_info_count' => 'integer', 'npm_low_count' => 'integer', 'npm_moderate_count' => 'integer', 'npm_high_count' => 'integer', 'npm_critical_count' => 'integer', 'finding_codes' => 'array', 'tool_versions' => 'array', 'size_bytes' => 'integer', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
