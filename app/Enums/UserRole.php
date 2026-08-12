<?php

namespace App\Enums;

enum UserRole: string
{
    case CountyOfficial = 'county-official';
    case CountyAdmin = 'county-admin';
    case Assessor = 'assessor';
    case DevelopmentPartner = 'development-partner';
    case TopManagement = 'top-management';
    case DevolutionAdmin = 'devolution-admin';
    case PlatformAdmin = 'platform-admin';

    public function label(): string
    {
        return match ($this) {
            self::CountyOfficial => 'County official',
            self::CountyAdmin => 'County administrator',
            self::Assessor => 'Independent assessor',
            self::DevelopmentPartner => 'Development partner',
            self::TopManagement => 'Top management',
            self::DevolutionAdmin => 'Devolution administrator',
            self::PlatformAdmin => 'Platform administrator',
        };
    }

    public function hasNationalScope(): bool
    {
        return in_array($this, [self::DevolutionAdmin, self::PlatformAdmin]);
    }

    public function hasAssignedCountyScope(): bool
    {
        return in_array($this, [self::Assessor, self::DevelopmentPartner, self::TopManagement]);
    }

    /** @return list<ProgrammePermission> */
    public function permissions(): array
    {
        $viewCounty = [ProgrammePermission::ViewDashboard, ProgrammePermission::ViewCountyData, ProgrammePermission::ViewAnalytics, ProgrammePermission::ViewSupportDesk, ProgrammePermission::SubmitSupportTickets];

        $permissions = match ($this) {
            self::CountyOfficial => [...$viewCounty, ProgrammePermission::UploadEvidence, ProgrammePermission::ViewGrants, ProgrammePermission::ViewMonitoringEvaluation, ProgrammePermission::SubmitIndicatorData, ProgrammePermission::ViewProjects, ProgrammePermission::SubmitProjectUpdates, ProgrammePermission::ViewPartnerCoordination, ProgrammePermission::ViewDswg, ProgrammePermission::ParticipateDswg, ProgrammePermission::ManageDswgActions, ProgrammePermission::ViewIgrResolutions, ProgrammePermission::UpdateIgrResolutions, ProgrammePermission::ViewCitizenCases, ProgrammePermission::RespondCitizenCases, ProgrammePermission::ViewTravelClearance, ProgrammePermission::SubmitTravelRequests, ProgrammePermission::ViewLearning, ProgrammePermission::EnrollLearning, ProgrammePermission::ViewKnowledge, ProgrammePermission::ContributeKnowledge],
            self::CountyAdmin => [...$viewCounty, ProgrammePermission::UploadEvidence, ProgrammePermission::SubmitAssessment, ProgrammePermission::ManageCountyUsers, ProgrammePermission::ViewGrants, ProgrammePermission::ViewMonitoringEvaluation, ProgrammePermission::SubmitIndicatorData, ProgrammePermission::ViewProjects, ProgrammePermission::ManageProjects, ProgrammePermission::SubmitProjectUpdates, ProgrammePermission::ViewPartnerCoordination, ProgrammePermission::ViewDswg, ProgrammePermission::ParticipateDswg, ProgrammePermission::ManageDswgActions, ProgrammePermission::ViewIgrResolutions, ProgrammePermission::UpdateIgrResolutions, ProgrammePermission::ViewCitizenCases, ProgrammePermission::ManageCitizenCases, ProgrammePermission::RespondCitizenCases, ProgrammePermission::ViewTravelClearance, ProgrammePermission::SubmitTravelRequests, ProgrammePermission::ApproveTravelRequests, ProgrammePermission::ViewLearning, ProgrammePermission::EnrollLearning, ProgrammePermission::ViewKnowledge, ProgrammePermission::ViewKnowledgeAnalytics, ProgrammePermission::ContributeKnowledge],
            self::Assessor => [...$viewCounty, ProgrammePermission::ReviewAssessment, ProgrammePermission::ScoreAssessment, ProgrammePermission::ViewMonitoringEvaluation, ProgrammePermission::VerifyIndicatorData, ProgrammePermission::ViewProjects, ProgrammePermission::VerifyProjectUpdates, ProgrammePermission::ViewPartnerCoordination, ProgrammePermission::ViewDswg, ProgrammePermission::ViewIgrResolutions, ProgrammePermission::ViewLearning, ProgrammePermission::EnrollLearning, ProgrammePermission::ViewKnowledge, ProgrammePermission::ViewKnowledgeAnalytics, ProgrammePermission::ContributeKnowledge],
            self::DevelopmentPartner => [...$viewCounty, ProgrammePermission::ViewGrants, ProgrammePermission::ViewNationalReports, ProgrammePermission::ViewMonitoringEvaluation, ProgrammePermission::SubmitIndicatorData, ProgrammePermission::ViewProjects, ProgrammePermission::SubmitProjectUpdates, ProgrammePermission::ViewPartnerCoordination, ProgrammePermission::SubmitPartnerData, ProgrammePermission::ViewDswg, ProgrammePermission::ParticipateDswg, ProgrammePermission::ManageDswgActions, ProgrammePermission::ViewIgrResolutions, ProgrammePermission::UpdateIgrResolutions, ProgrammePermission::ViewLearning, ProgrammePermission::EnrollLearning, ProgrammePermission::ViewKnowledge, ProgrammePermission::ViewKnowledgeAnalytics, ProgrammePermission::ContributeKnowledge],
            self::TopManagement => [...$viewCounty, ProgrammePermission::ApproveAssessment, ProgrammePermission::ViewGrants, ProgrammePermission::ViewNationalReports, ProgrammePermission::ViewMonitoringEvaluation, ProgrammePermission::VerifyIndicatorData, ProgrammePermission::ViewProjects, ProgrammePermission::VerifyProjectUpdates, ProgrammePermission::ViewPartnerCoordination, ProgrammePermission::ApprovePartnerAgreements, ProgrammePermission::ResolveCollaborationAlerts, ProgrammePermission::ViewDswg, ProgrammePermission::VerifyDswgActions, ProgrammePermission::ViewIgrResolutions, ProgrammePermission::CloseIgrResolutions, ProgrammePermission::ViewCitizenCases, ProgrammePermission::ResolveCitizenCases, ProgrammePermission::ViewTravelClearance, ProgrammePermission::ApproveTravelRequests, ProgrammePermission::FinanceClearTravel, ProgrammePermission::ViewDepartmentalPerformance, ProgrammePermission::ReviewPerformancePlans, ProgrammePermission::ViewLearning, ProgrammePermission::ReviewLearning, ProgrammePermission::EnrollLearning, ProgrammePermission::ViewKnowledge, ProgrammePermission::ViewKnowledgeAnalytics, ProgrammePermission::ContributeKnowledge, ProgrammePermission::CurateKnowledge, ProgrammePermission::ViewIntegrations, ProgrammePermission::ResolveIntegrationExceptions, ProgrammePermission::ViewOperations, ProgrammePermission::ViewDataGovernance, ProgrammePermission::ViewSecurityGovernance],
            self::DevolutionAdmin => [...$viewCounty, ProgrammePermission::ApproveAssessment, ProgrammePermission::ManageAssessmentConfiguration, ProgrammePermission::ManageGrants, ProgrammePermission::ViewGrants, ProgrammePermission::ViewNationalReports, ProgrammePermission::ManageCountyUsers, ProgrammePermission::ManageReferenceData, ProgrammePermission::ManageWorkflows, ProgrammePermission::ViewAuditTrail, ProgrammePermission::ViewMonitoringEvaluation, ProgrammePermission::ManageIndicators, ProgrammePermission::VerifyIndicatorData, ProgrammePermission::ManageRecords, ProgrammePermission::ViewProjects, ProgrammePermission::ManageProjects, ProgrammePermission::VerifyProjectUpdates, ProgrammePermission::ViewPartnerCoordination, ProgrammePermission::ManagePartners, ProgrammePermission::ResolveCollaborationAlerts, ProgrammePermission::ViewDswg, ProgrammePermission::ManageDswg, ProgrammePermission::ManageDswgActions, ProgrammePermission::VerifyDswgActions, ProgrammePermission::ViewIgrResolutions, ProgrammePermission::ManageIgrResolutions, ProgrammePermission::UpdateIgrResolutions, ProgrammePermission::CloseIgrResolutions, ProgrammePermission::ViewCitizenCases, ProgrammePermission::ManageCitizenCases, ProgrammePermission::RespondCitizenCases, ProgrammePermission::ResolveCitizenCases, ProgrammePermission::ViewTravelClearance, ProgrammePermission::SubmitTravelRequests, ProgrammePermission::ApproveTravelRequests, ProgrammePermission::FinanceClearTravel, ProgrammePermission::ViewDepartmentalPerformance, ProgrammePermission::ManagePerformanceCycles, ProgrammePermission::SubmitPerformancePlans, ProgrammePermission::ReviewPerformancePlans, ProgrammePermission::ViewLearning, ProgrammePermission::ManageLearning, ProgrammePermission::EnrollLearning, ProgrammePermission::ViewKnowledge, ProgrammePermission::ViewKnowledgeAnalytics, ProgrammePermission::ContributeKnowledge, ProgrammePermission::ManageKnowledge, ProgrammePermission::ViewIntegrations, ProgrammePermission::ManageIntegrations, ProgrammePermission::ResolveIntegrationExceptions, ProgrammePermission::ViewOperations, ProgrammePermission::ViewDataGovernance, ProgrammePermission::ManageDataGovernance, ProgrammePermission::ViewSecurityGovernance, ProgrammePermission::ManageSecurityGovernance, ProgrammePermission::CertifyAccess],
            self::PlatformAdmin => [...$viewCounty, ProgrammePermission::ViewNationalReports, ProgrammePermission::ManageUserAccess, ProgrammePermission::ConfigurePlatform, ProgrammePermission::ManageAssessmentConfiguration, ProgrammePermission::ManageReferenceData, ProgrammePermission::ManageWorkflows, ProgrammePermission::ViewAuditTrail, ProgrammePermission::ViewMonitoringEvaluation, ProgrammePermission::ManageIndicators, ProgrammePermission::ManageRecords, ProgrammePermission::ViewProjects, ProgrammePermission::ViewPartnerCoordination, ProgrammePermission::ManagePartners, ProgrammePermission::ViewDswg, ProgrammePermission::ViewIgrResolutions, ProgrammePermission::ViewDepartmentalPerformance, ProgrammePermission::ViewLearning, ProgrammePermission::ManageLearning, ProgrammePermission::ReviewLearning, ProgrammePermission::ViewKnowledge, ProgrammePermission::ViewKnowledgeAnalytics, ProgrammePermission::ManageKnowledge, ProgrammePermission::CurateKnowledge, ProgrammePermission::ViewIntegrations, ProgrammePermission::ManageIntegrations, ProgrammePermission::ResolveIntegrationExceptions, ProgrammePermission::ViewOperations, ProgrammePermission::ManageOperations, ProgrammePermission::ViewDataGovernance, ProgrammePermission::ManageDataGovernance, ProgrammePermission::ViewSecurityGovernance, ProgrammePermission::ManageSecurityGovernance, ProgrammePermission::CertifyAccess],
        };

        return match ($this) {
            self::CountyAdmin => [...$permissions, ProgrammePermission::ManageSupportTickets, ProgrammePermission::ResolveSupportTickets],
            self::DevolutionAdmin => [...$permissions, ProgrammePermission::ManageSupportTickets, ProgrammePermission::ResolveSupportTickets, ProgrammePermission::ConfigureSupportDesk, ProgrammePermission::ManageAnalytics],
            self::PlatformAdmin => [...$permissions, ProgrammePermission::ManageSupportTickets, ProgrammePermission::ResolveSupportTickets, ProgrammePermission::ConfigureSupportDesk, ProgrammePermission::PublishSupportDeskPolicy, ProgrammePermission::ApproveReferenceData, ProgrammePermission::ManageAnalytics, ProgrammePermission::ApproveAnalytics, ProgrammePermission::ApproveReportSchedules, ProgrammePermission::ViewUserActivity],
            self::TopManagement => [...$permissions, ProgrammePermission::PublishSupportDeskPolicy, ProgrammePermission::ApproveAnalytics, ProgrammePermission::ApproveReportSchedules],
            default => $permissions,
        };
    }
}
