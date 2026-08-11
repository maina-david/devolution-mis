<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case EvidenceCollection = 'evidence_collection';
    case Submitted = 'submitted';
    case UnderAssessment = 'under_assessment';
    case Assessed = 'assessed';
    case Approved = 'approved';
    case Published = 'published';
}
