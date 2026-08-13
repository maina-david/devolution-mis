# Enterprise Readiness

Engineering completion and enterprise acceptance are separate claims.

## Engineering evidence currently available

- Repeatable PostgreSQL migrations and realistic seed baseline
- UUID, foreign-key, soft-delete, check, index, and trigger coverage
- Role and county/portfolio authorization tests
- Fourteen module workspaces and navigation references
- Governed workflows, audit evidence, document controls, exports, dashboards, and operational scheduling
- Local and GitHub automated quality gates

## Acceptance evidence still required

- Accountable business-owner and data-owner approvals
- Representative county, national, assessor, partner, citizen, and administrator UAT
- Production integration agreements and certified adapters
- Production-like performance, concurrency, security, accessibility, and resilience evidence
- Backup/restore and disaster-recovery exercises against approved infrastructure
- SOC/SIEM, key management, storage lifecycle, network, and operational acceptance
- Signed/tagged release provenance, protected-branch policy, and formal release authorization

## Repository-owned completion work

The August 2026 local completion increment adds governed legacy ACPA reconstruction, personal analytics filter views with county-disaggregated charts, and checksummed sub-county/ward hierarchy imports. The hierarchy now embeds a controlled 18 July 2026 source snapshot covering 47 counties, 290 constituency/sub-county parent units and all 1,450 IEBC wards, including 2022 registered-voter counts, source checksums and an evidenced correction of the Gatundu North parent assignment against the Kenya Gazette and Kiambu ADP. Paginated county-filtered registers and governed bulk-import entry points expose the hierarchy in the reference-data workspace. Boundary polygons, authoritative historical archives and accountable acceptance remain external and are not represented as complete.

The active repository-only programme is tracked in the controlled vault and currently covers full legacy ACPA reconstruction, richer analytics/drill-downs, county administrative hierarchy, complete localization extraction, automated browser/accessibility/security/performance evidence, deeper local module workflows, and governed disposition tooling for legacy/unpinned records. These items can advance without external systems or approvals, but their engineering completion does not substitute for the acceptance evidence above.

The first increment adds personal named analytics filter views with one default per user, automatic default application, owner-only deletion, audit evidence, accessible controls, localized server outcomes, UUID keys and soft deletion.

The analytics-depth increment completes the repository-owned saved drill-down baseline: named views retain dashboard, widget, county, date, visualization and monthly/quarterly/yearly grain; invalid dashboard/widget relationships and out-of-scope dashboards fail closed. Governed widgets support bar, line and area charts backed by county-scoped bounded time series (maximum 36 monthly, 20 quarterly or 10 yearly points) with a five-minute scope-keyed cache. CSV, XLSX, JSON and PDF scheduled artifacts carry identical visualization, grain and trend evidence. Focused analytics/accessibility verification passed 19 tests and 229 assertions; Pint, TypeScript, scoped ESLint, localization regression and production build passed. KPI semantics and accountable analytics acceptance remain external.

The second repository-only increment adds scope-safe IGR dependency-path and bottleneck analytics, privacy-thresholded citizen recurring-issue/monthly/backlog/resolution-time analytics, framework catalogue and placeholder parity for English/Kiswahili/French, a seven-journey Playwright keyboard/focus/landmark/name/reflow/reduced-motion/contrast harness, and hostile authorization fuzz coverage across five unprivileged roles. These are local engineering controls; professional translation, assistive-technology certification, independent penetration testing and production-like resilience acceptance remain external.

The localization extraction programme now covers the authenticated navigation registry, settings navigation, reusable data-table controls, searchable selects, table empty states and account actions in English, Kiswahili and French. Knowledge, learning, security-governance, IGR, integration and data-governance mutation outcomes, plus controlled CSV/XLSX import validation, are also extracted. The complete learning workspace—including catalogue, cohorts, course authoring, reproducible question banks, classrooms, offline synchronization, governed assets, assessments and certificates—uses synchronized catalogues, locale-aware dates and localized assistive labels and operational values. Its JSX literal gate is zero. A source audit records 1,730 remaining frontend literals across 103 files and 654 backend candidates and fails when either corpus increases; this is a regression control, not a claim that application-wide extraction is complete.

The shared evidence and document-records action surface is now fully extracted across preview, OCR attempts, metadata, immutable versions, legal holds, disposition review and secure-destruction evidence. Dates and numeric evidence on this surface use the active user locale instead of a fixed English locale.

Evidence upload, verification, metadata, versioning, legal-hold and controlled-disposition server outcomes and preview errors are localized as well. The backend audit now includes literal `abort`, `abort_if` and `abort_unless` response messages; this deliberately raises the disclosed baseline by exposing previously unmeasured debt.

The migration control workspace inventories explicit legacy/unpinned reference lineage across 21 product record types without assigning modern releases to retained history. It now supports per-record checksum-bound proposals for explicit legacy retention, verified release pinning or operational deprecation, optional validated successor links, independent review, third-person application, stale-record rejection and database-immutable terminal evidence. Accountable approval of actual dispositions remains external.

Learning assessment depth now includes immutable checksum-versioned question banks, objective-group variants, difficulty metadata, deterministic bounded per-attempt selection, randomized option order, attempt lineage and rejection of answers outside the selected variant. This is an engineering baseline; instructional-design approval and authoritative question content remain external.

Local mixed-route resilience evidence runs 100 HTTPS requests across liveness and readiness with concurrency 10. The latest 2026-08-13 run completed with zero failures at 78.34 requests/sec and p95 203 ms. A second phase detected a controlled HTTP 404 and then completed 30 recovery requests at concurrency 5 with zero failures and p95 75 ms. Accessibility automation now covers seven public and role journeys, including the reconciliation workspace. The final full PHPUnit regression passed all 628 tests and 10,672 assertions. This is development-host evidence only, not production-topology capacity certification.

The controlled Obsidian vault is the source for detailed ToR traceability, implementation evidence, gates, decisions, and approval artifacts.
