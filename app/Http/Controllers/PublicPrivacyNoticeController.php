<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Lang;
use Inertia\Inertia;
use Inertia\Response;

class PublicPrivacyNoticeController extends Controller
{
    public function __invoke(): Response
    {
        $copy = Lang::get('privacy-notice');
        abort_unless(is_array($copy), 500, 'The privacy notice catalogue is unavailable.');

        return Inertia::render('privacy-notice', [
            'notice' => [
                'version' => (string) config('privacy.public_notice.version'),
                'issuedOn' => (string) config('privacy.public_notice.issued_on'),
                'approvalStatus' => (string) config('privacy.public_notice.approval_status'),
                'copy' => $copy,
            ],
        ]);
    }
}
