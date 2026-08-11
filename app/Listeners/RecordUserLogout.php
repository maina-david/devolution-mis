<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserActivityTracker;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class RecordUserLogout
{
    public function __construct(private Request $request, private UserActivityTracker $tracker, private AuditLogger $auditLogger) {}

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User || ! $this->request->hasSession()) {
            return;
        }

        $activitySession = $this->tracker->finish($this->request, $event->user);
        if ($activitySession !== null) {
            $this->auditLogger->record($event->user, $activitySession, 'auth.logout', 'User logged out.', metadata: ['guard' => $event->guard]);
        }
    }
}
