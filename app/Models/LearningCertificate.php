<?php

namespace App\Models;

use Database\Factories\LearningCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $issued_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['learning_enrollment_id', 'certificate_number', 'verification_code', 'content_checksum', 'final_score', 'issued_at', 'expires_at', 'issued_by'])]
class LearningCertificate extends Model
{
    /** @use HasFactory<LearningCertificateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['final_score' => 'decimal:2', 'issued_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<LearningEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(LearningEnrollment::class, 'learning_enrollment_id');
    }
}
