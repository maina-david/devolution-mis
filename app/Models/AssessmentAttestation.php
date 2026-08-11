<?php

namespace App\Models;

use Database\Factories\AssessmentAttestationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $content_checksum */
#[Fillable(['assessment_id', 'attested_by', 'attestor_title', 'statement', 'signature_method', 'content_checksum', 'attested_at', 'revoked_at', 'revoked_by', 'revocation_reason'])]
class AssessmentAttestation extends Model
{
    /** @use HasFactory<AssessmentAttestationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['attested_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
