---
paths:
  - 'app/{Actions,Models,Services,Http/Controllers,Http/Requests,Console/Commands}/**/*{ServiceDesk,SupportTicket}*.php,resources/js/pages/support-desk/**,database/migrations/*service_desk*,database/migrations/*support_ticket*,database/seeders/ServiceDeskPolicySeeder.php'
---

# Migrations Migrations Seeders

## Pin governed service desk policy at ticket creation
New support tickets must resolve a checksum-valid effective published ServiceDeskPolicy, validate category/channel from its catalogue, calculate deadlines with the pinned BusinessCalendar, and pin policy/calendar checksums. Policy publication requires an independent publisher plus active national tier-1 and tier-3 roster coverage. SLA monitoring uses the pinned policy; config fallback is legacy-ticket compatibility only.
