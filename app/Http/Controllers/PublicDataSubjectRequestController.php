<?php

namespace App\Http\Controllers;

use App\Actions\ReceiveDataSubjectRequest;
use App\Http\Requests\StorePublicDataSubjectRequestRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicDataSubjectRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('data-rights/index', [
            'noticeVersion' => (string) config('privacy.public_notice.version'),
            'targetDays' => (int) config('privacy.data_subject_request_target_days'),
        ]);
    }

    public function store(StorePublicDataSubjectRequestRequest $request, ReceiveDataSubjectRequest $action): RedirectResponse
    {
        $attributes = $request->safe()->only([
            'request_type',
            'requester_name',
            'requester_contact',
            'contact_channel',
            'scope',
        ]);
        $privacyRequest = $action->handle($attributes, now(), null, [
            'intake_channel' => 'public_web',
            'locale' => app()->getLocale(),
            'privacy_notice_version' => $request->validated('privacy_notice_version'),
            'consent_given' => true,
        ]);

        return redirect()->route('data-rights.receipt')->with('data_rights_receipt', [
            'reference' => $privacyRequest->reference,
            'receivedAt' => $privacyRequest->received_at->toIso8601String(),
            'dueAt' => $privacyRequest->due_at->toIso8601String(),
        ]);
    }

    public function receipt(Request $request): Response|RedirectResponse
    {
        $receipt = $request->session()->get('data_rights_receipt');
        if (! is_array($receipt)) {
            return redirect()->route('data-rights.index');
        }

        return Inertia::render('data-rights/receipt', ['receipt' => $receipt]);
    }
}
