<?php

namespace App\Models;

use Database\Factories\CriterionEvidenceRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $assessment_criterion_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $minimum_documents
 * @property list<string> $allowed_categories
 * @property list<string> $accepted_mime_types
 * @property bool $requires_verification
 * @property bool $is_mandatory
 */
#[Fillable(['assessment_criterion_id', 'code', 'name', 'description', 'minimum_documents', 'allowed_categories', 'accepted_mime_types', 'requires_verification', 'is_mandatory'])]
class CriterionEvidenceRequirement extends Model
{
    /** @use HasFactory<CriterionEvidenceRequirementFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['minimum_documents' => 1, 'requires_verification' => true, 'is_mandatory' => true];

    protected function casts(): array
    {
        return ['allowed_categories' => 'array', 'accepted_mime_types' => 'array', 'requires_verification' => 'boolean', 'is_mandatory' => 'boolean'];
    }

    /** @return BelongsTo<AssessmentCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AssessmentCriterion::class, 'assessment_criterion_id');
    }
}
