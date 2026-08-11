<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\UserActivityTracker;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class RecordUserLogin
{
    public function __construct(private Request $request, private UserActivityTracker $tracker, private AuditLogger $auditLogger) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! $this->request->hasSession()) {
            return;
        }

        $activitySession = $this->tracker->start($this->request, $event->user);
        $this->auditLogger->record($event->user, $activitySession, 'auth.login', 'User authenticated successfully.', metadata: ['guard' => $event->guard, 'remember' => $event->remember, 'user_agent' => $this->request->userAgent()]);
    }
}
