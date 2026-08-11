---
paths:
  - 'app/Http/Middleware/TrackUserActivity.php,app/Services/AuditLogger.php,app/Models/UserActivitySession.php,app/Models/UserPageView.php'
---

# Models

## Separate page access from domain audit
Record successful Inertia page access in the append-only user_page_views ledger, not audit_events, to preserve domain audit semantics and query budgets. Correlate governed AuditLogger events through activity_session_id metadata; presence remains mutable in user_activity_sessions and inactive sessions are closed by the scheduler.
