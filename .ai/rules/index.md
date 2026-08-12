# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/Models/{Role,Permission}.php,database/migrations/*{roles,permissions}*,config/permission.php | .ai/rules/app-models-migrations.md |
| app/Console/Commands/MonitorSupportTicketSlas.php,config/service-desk.php,tests/Feature/{SupportDeskWorkflowTest,PerformanceAssuranceTest}.php | .ai/rules/commands-feature.md |
| app/{Actions,Console/Commands}/**/*IdentityLifecycle*.php | .ai/rules/commands.md |
| app/Http/Controllers/EvidenceController.php,resources/js/components/evidence-row-action.tsx,tests/Feature/DocumentRecordsGovernanceTest.php | .ai/rules/components-feature.md |
| app/Jobs/ExtractDocumentText.php,app/Models/DocumentExtraction*.php,database/migrations/*document_extraction*,app/Services/ProgrammeWorkspaceData.php,resources/js/components/evidence-row-action.tsx | .ai/rules/components.md |
| app/Actions/PublishWorkflowVersion.php,app/Models/WorkflowVersion.php,database/migrations/*workflow_versions*,app/Http/Controllers/WorkflowDefinitionController.php | .ai/rules/controllers.md |
| app/Models/County.php,app/**/**County*,resources/js/**,database/data/county-identity.json,public/images/counties/** | .ai/rules/counties.md |
| app/{Actions,Services,Http/Controllers,Http/Requests}/**/*{Import,Migration,Tabular}*.php,resources/js/pages/data-migrations/**,tests/Feature/*{Import,Migration}*Test.php | .ai/rules/data-migrations-feature.md |
| {resources/js/**,lang/**,app/Http/**,app/Enums/SupportedLocale.php,tests/**} | .ai/rules/enums.md |
| {app/Actions/**,routes/**,resources/js/**,database/factories/**} | .ai/rules/factories.md |
| resources/js/components/{app-content,app-shell,input-error}.tsx,resources/js/layouts/auth/**,resources/js/pages/{welcome,help,faqs}.tsx,resources/js/pages/auth/**,tests/Feature/AccessibilityContractTest.php | .ai/rules/feature.md |
| {resources/js/**,lang/**,app/Http/**,routes/**,tests/**} | .ai/rules/http.md |
| app/{Actions,Models,Services,Http/Controllers,Http/Requests}/**/*Uat*.php,database/migrations/*uat*,database/seeders/ChangeReadinessSeeder.php,resources/js/components/uat-governance-workspace.tsx,tests/Feature/UatGovernanceWorkflowTest.php | .ai/rules/js-components-feature.md |
| app/{Actions,Models,Services,Http/Controllers,Http/Requests,Console/Commands}/**/*Audit*.php,database/migrations/*audit_assurance*,resources/js/**/*audit-assurance*,tests/Feature/{AuditTrailTest,AuditAssuranceTest}.php | .ai/rules/js-feature.md |
| routes/**,resources/js/** | .ai/rules/js.md |
| app/{Actions,Models,Http/Controllers,Http/Requests}/**/*Learning*.php,database/migrations/*learning_offline_package*,resources/js/pages/learning/**,tests/Feature/LearningOfflinePackageTest.php | .ai/rules/learning-feature.md |
| app/{Actions,Models,Services,Http/Controllers}/**,database/migrations/**,tests/Feature/** | .ai/rules/migrations-feature.md |
| {routes/**,app/Http/Responses/**,app/Models/User.php,database/migrations/**,resources/js/**,tests/**} | .ai/rules/migrations-js.md |
| app/{Actions,Models,Services,Http/Controllers,Http/Requests,Console/Commands}/**/*{ServiceDesk,SupportTicket}*.php,resources/js/pages/support-desk/**,database/migrations/*service_desk*,database/migrations/*support_ticket*,database/seeders/ServiceDeskPolicySeeder.php | .ai/rules/migrations-migrations-seeders.md |
| database/migrations/**,database/seeders/** | .ai/rules/migrations-seeders.md |
| app/Actions/StartWorkflow.php,app/Actions/TransitionWorkflow.php,app/Services/WorkflowRuleEvaluator.php,app/Services/WorkflowSlaMonitor.php,app/Models/WorkflowInstance.php,app/Models/WorkflowTransition.php,database/migrations/*workflow_instances*,database/migrations/*workflow_transitions* | .ai/rules/migrations.md |
| app/Actions/{StartWorkflow,TransitionWorkflow}.php,app/Services/BusinessTimeCalculator.php,app/Models/{WorkflowInstance,BusinessCalendar,BusinessCalendarHoliday}.php,database/migrations/*business_calendar* | .ai/rules/models-migrations.md |
| app/Http/Middleware/TrackUserActivity.php,app/Services/AuditLogger.php,app/Models/UserActivitySession.php,app/Models/UserPageView.php | .ai/rules/models.md |
| app/Actions/RetryFailedQueueJob.php,app/Console/Commands/RecordOperationalMeasurement.php,app/Http/Controllers/OperationsController.php,app/Models/QueueRecoveryAttempt.php,database/migrations/*queue_recovery_attempts*,resources/js/pages/operations/** | .ai/rules/operations.md |
| app/Actions/RunHttpPerformanceProbe.php,app/Console/Commands/RunHttpPerformanceProbeCommand.php,app/Models/PerformanceTestRun.php,database/migrations/*performance_test_runs*,tests/Feature/PerformanceAssuranceTest.php,resources/js/pages/operations/** | .ai/rules/pages-operations.md |
| app/{Actions,Models,Http/Controllers,Http/Requests}/**/*IdentityLifecycle*.php,database/migrations/*identity_lifecycle*,resources/js/pages/security-governance/**,tests/Feature/IdentityLifecycleWorkflowTest.php | .ai/rules/pages-security-governance-feature.md |
| app/{Actions,Models,Services,Http/Controllers,Http/Requests}/**/*ProjectSchedule*.php,database/migrations/*project_schedule_baseline*,resources/js/pages/projects/**, app/{Actions,Models,Services,Http/Controllers,Http/Requests}/**/*Project*.php,database/migrations/*project_resource*,resources/js/pages/projects/** | .ai/rules/projects.md |
| app/{Actions,Models,Http/Controllers,Http/Requests}/**/*Assessment*.php,database/migrations/*assessment_scorecard*.php, app/{Actions,Models,Http/Controllers,Http/Requests}/**/*Assessment*.php,database/migrations/*assessment*.php | .ai/rules/requests-migrations.md |
| app/{Actions,Models,Http/Controllers,Http/Requests,Services}/**/*Assessment*.php,database/migrations/*assessment*.php | .ai/rules/requests-services-migrations.md |
| app/{Actions,Models,Http/Controllers,Http/Requests,Services}/**/*VirtualClassroom*.php | .ai/rules/requests-services.md |
| app/{Actions,Models,Http/Controllers,Http/Requests}/**/*Knowledge*.php, app/{Actions,Models,Http/Controllers,Http/Requests}/**/*KnowledgeCommunityReport*.php | .ai/rules/requests.md |
| app/{Actions,Models,Http/Controllers,Http/Requests,Console/Commands}/**/*SecurityIncident*.php,database/migrations/*security_incident*,resources/js/pages/security-governance/**,tests/Feature/SecurityIncidentWorkflowTest.php | .ai/rules/security-governance-feature.md |
| app/Actions/RunSupplyChainScan.php,app/Models/SupplyChainScan.php,database/migrations/*supply_chain_scans*,app/Http/Controllers/SecurityGovernanceController.php,resources/js/pages/security-governance/** | .ai/rules/security-governance.md |
| app/Models/User.php,app/Services/ProgrammeAuthorization.php,database/migrations/**,database/seeders/** | .ai/rules/seeders.md |
| app/Services/LocalDocumentTextExtractor.php,config/repository.php,tests/Feature/DocumentTextExtractionTest.php | .ai/rules/services-feature.md |
| app/Services/DocumentSecurityScanner.php,app/Services/OperationalReadinessCheck.php,config/repository.php,tests/Feature/{DocumentRecordsGovernanceTest,OperationalReadinessTest}.php | .ai/rules/services-services-feature.md |
| app/Http/{Controllers/AccessControlController.php,Requests/Update*PermissionsRequest.php}|resources/js/pages/access-control/**|app/Services/ProgrammeAuthorization.php | .ai/rules/services.md |
| app/{Actions,Services,Http/Controllers/Settings,Http/Requests/Settings}/**|database/migrations/*create_users_table.php|resources/js/{pages/settings,components}/** | .ai/rules/settingscomponents.md |
| app/{Actions,Models,Services,Http/Controllers,Http/Requests,Console/Commands}/**/*SupportTicket*.php,resources/js/pages/support-desk/**,database/migrations/*support_ticket*,database/seeders/SupportDeskSeeder.php | .ai/rules/support-desk-migrations-seeders.md |
| app/Services/WorkflowSimulator.php,app/Http/Controllers/WorkflowDefinitionController.php,resources/js/pages/workflows/** | .ai/rules/workflows.md |
