<?php

namespace App\Actions;

use App\Models\CitizenCase;
use App\Models\CitizenCaseMessage;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AddCitizenCaseMessage
{
    public function __construct(private StoreCitizenCaseAttachment $storeAttachment, private AuditLogger $auditLogger) {}

    public function handle(CitizenCase $case, User $actor, string $body, string $visibility, ?UploadedFile $attachment = null, string $sourceType = 'born_digital'): CitizenCaseMessage
    {
        return DB::transaction(function () use ($case, $actor, $body, $visibility, $attachment, $sourceType): CitizenCaseMessage {
            $message = $case->messages()->create(['sender_user_id' => $actor->id, 'direction' => $visibility === 'public' ? 'outbound' : 'internal', 'visibility' => $visibility, 'channel' => 'web', 'body' => $body, 'delivery_status' => 'recorded', 'posted_at' => now()]);
            if ($attachment) {
                $this->storeAttachment->handle($case, $attachment, $sourceType, $actor, $message);
            }
            if ($visibility === 'public' && $case->first_responded_at === null) {
                $case->update(['first_responded_at' => now()]);
            }
            $this->auditLogger->record($actor, $message, 'citizen_case.message_added', "A {$visibility} case message was recorded.", $case->county_id);

            return $message->refresh();
        });
    }
}
