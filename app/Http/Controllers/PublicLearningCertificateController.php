<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyLearningCertificateRequest;
use App\Models\LearningCertificate;
use Inertia\Inertia;
use Inertia\Response;

class PublicLearningCertificateController extends Controller
{
    public function __invoke(VerifyLearningCertificateRequest $request): Response
    {
        $code = $request->validated('code');
        $certificate = is_string($code)
            ? LearningCertificate::query()
                ->with(['enrollment.user:id,name', 'enrollment.course:id,code,title'])
                ->where('verification_code', $code)
                ->first()
            : null;

        return Inertia::render('learning/certificate-verification', [
            'query' => $code,
            'searched' => is_string($code),
            'certificate' => $certificate ? [
                'number' => $certificate->certificate_number,
                'learner' => $certificate->enrollment->user->name,
                'courseCode' => $certificate->enrollment->course->code,
                'courseTitle' => $certificate->enrollment->course->title,
                'finalScore' => (string) $certificate->final_score,
                'issuedAt' => $certificate->issued_at->toDateString(),
                'expiresAt' => $certificate->expires_at?->toDateString(),
                'status' => $certificate->expires_at?->isPast() ? 'expired' : 'valid',
                'checksum' => $certificate->content_checksum,
            ] : null,
        ]);
    }
}
