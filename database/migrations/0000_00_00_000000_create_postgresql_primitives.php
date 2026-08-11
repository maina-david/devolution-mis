<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
--
-- PostgreSQL database dump
--


-- Dumped from database version 17.10 (Homebrew)
-- Dumped by pg_dump version 17.10 (Homebrew)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: btree_gist; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS btree_gist WITH SCHEMA public;


--
-- Name: EXTENSION btree_gist; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION btree_gist IS 'support for indexing common datatypes in GiST';


--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


--
-- Name: prevent_applied_data_migration_batch_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_applied_data_migration_batch_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF OLD.status = 'applied' THEN
            RAISE EXCEPTION 'Applied data migration batches are immutable';
        END IF;
        RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
    END;
    $$;


--
-- Name: prevent_applied_data_migration_row_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_applied_data_migration_row_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF OLD.applied_at IS NOT NULL THEN
            RAISE EXCEPTION 'Applied data migration rows are immutable';
        END IF;
        RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
    END;
    $$;


--
-- Name: prevent_audit_event_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_audit_event_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'audit_events is append-only';
    END;
    $$;


--
-- Name: prevent_decided_corrective_update_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_decided_corrective_update_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.status IN ('verified', 'rejected') THEN
        RAISE EXCEPTION 'Decided corrective update evidence is immutable';
    END IF;
    RETURN NEW;
END;
$$;


--
-- Name: prevent_executed_document_disposition_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_executed_document_disposition_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.status = 'executed' THEN
        RAISE EXCEPTION 'Executed document disposition evidence is immutable';
    END IF;
    RETURN NEW;
END;
$$;


--
-- Name: prevent_historical_metric_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_historical_metric_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        RAISE EXCEPTION 'Historical metric evidence is immutable';
    END;
    $$;


--
-- Name: prevent_published_business_calendar_holiday_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_published_business_calendar_holiday_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE calendar_status text;
BEGIN
    SELECT status INTO calendar_status
    FROM business_calendars
    WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.business_calendar_id ELSE NEW.business_calendar_id END;
    IF calendar_status = 'published' THEN
        RAISE EXCEPTION 'Published business calendar exceptions are immutable';
    END IF;
    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$;


--
-- Name: prevent_published_business_calendar_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_published_business_calendar_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.status = 'published' THEN
        RAISE EXCEPTION 'Published business calendars are immutable';
    END IF;
    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$;


--
-- Name: prevent_published_reference_release_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_published_reference_release_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF OLD.status = 'published' THEN
            RAISE EXCEPTION 'Published reference-data releases are immutable';
        END IF;
        RETURN NEW;
    END;
    $$;


--
-- Name: prevent_sector_hierarchy_cycle(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.prevent_sector_hierarchy_cycle() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
    BEGIN
        IF NEW.parent_sector_id IS NULL THEN
            RETURN NEW;
        END IF;

        IF EXISTS (
            WITH RECURSIVE ancestors AS (
                SELECT id, parent_sector_id
                FROM sectors
                WHERE id = NEW.parent_sector_id
                UNION ALL
                SELECT sectors.id, sectors.parent_sector_id
                FROM sectors
                INNER JOIN ancestors ON sectors.id = ancestors.parent_sector_id
            )
            SELECT 1 FROM ancestors WHERE id = NEW.id
        ) THEN
            RAISE EXCEPTION 'Sector hierarchy cannot contain a cycle.' USING ERRCODE = '23514';
        END IF;

        RETURN NEW;
    END;
    $$;


--
-- Name: protect_approved_indicator_definition(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_approved_indicator_definition() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.status = 'approved' THEN
        RAISE EXCEPTION 'Approved indicator definitions are immutable; create a new version';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;


--
-- Name: protect_assessment_result_publication(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_assessment_result_publication() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'Published assessment results are immutable';
END;
$$;


--
-- Name: protect_assessment_scorecard_component(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_assessment_scorecard_component() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    version_status varchar;
BEGIN
    CASE TG_TABLE_NAME
        WHEN 'assessment_functions' THEN
            SELECT status INTO version_status FROM assessment_scorecard_versions
            WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.assessment_scorecard_version_id ELSE NEW.assessment_scorecard_version_id END;
        WHEN 'assessment_thematic_areas' THEN
            SELECT v.status INTO version_status
            FROM assessment_scorecard_versions v
            JOIN assessment_functions f ON f.assessment_scorecard_version_id = v.id
            WHERE f.id = CASE WHEN TG_OP = 'DELETE' THEN OLD.assessment_function_id ELSE NEW.assessment_function_id END;
        WHEN 'assessment_standards' THEN
            SELECT v.status INTO version_status
            FROM assessment_scorecard_versions v
            JOIN assessment_functions f ON f.assessment_scorecard_version_id = v.id
            JOIN assessment_thematic_areas t ON t.assessment_function_id = f.id
            WHERE t.id = CASE WHEN TG_OP = 'DELETE' THEN OLD.assessment_thematic_area_id ELSE NEW.assessment_thematic_area_id END;
        WHEN 'assessment_criteria' THEN
            SELECT v.status INTO version_status
            FROM assessment_scorecard_versions v
            JOIN assessment_functions f ON f.assessment_scorecard_version_id = v.id
            JOIN assessment_thematic_areas t ON t.assessment_function_id = f.id
            JOIN assessment_standards s ON s.assessment_thematic_area_id = t.id
            WHERE s.id = CASE WHEN TG_OP = 'DELETE' THEN OLD.assessment_standard_id ELSE NEW.assessment_standard_id END;
        WHEN 'criterion_evidence_requirements' THEN
            SELECT v.status INTO version_status
            FROM assessment_scorecard_versions v
            JOIN assessment_functions f ON f.assessment_scorecard_version_id = v.id
            JOIN assessment_thematic_areas t ON t.assessment_function_id = f.id
            JOIN assessment_standards s ON s.assessment_thematic_area_id = t.id
            JOIN assessment_criteria c ON c.assessment_standard_id = s.id
            WHERE c.id = CASE WHEN TG_OP = 'DELETE' THEN OLD.assessment_criterion_id ELSE NEW.assessment_criterion_id END;
    END CASE;

    IF version_status IN ('published', 'retired') THEN
        RAISE EXCEPTION 'Released assessment scorecard components are immutable';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;


--
-- Name: protect_assessment_scorecard_version(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_assessment_scorecard_version() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF TG_OP = 'DELETE' AND OLD.status IN ('published', 'retired') THEN
        RAISE EXCEPTION 'Released assessment scorecard versions are immutable';
    END IF;

    IF TG_OP = 'UPDATE' AND OLD.status = 'retired' THEN
        RAISE EXCEPTION 'Retired assessment scorecard versions are immutable';
    END IF;

    IF TG_OP = 'UPDATE' AND OLD.status = 'published' THEN
        IF NEW.status <> 'retired'
            OR (to_jsonb(NEW) - ARRAY['status', 'effective_to', 'updated_at'])
                IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['status', 'effective_to', 'updated_at']) THEN
            RAISE EXCEPTION 'Published assessment scorecard versions may only be retired';
        END IF;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;


--
-- Name: protect_audit_assurance_runs(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_audit_assurance_runs() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Audit assurance evidence is immutable'; END; $$;


--
-- Name: protect_closed_evaluation_findings(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_closed_evaluation_findings() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF OLD.status = 'closed' THEN RAISE EXCEPTION 'Closed evaluation findings are immutable'; END IF; RETURN NEW; END; $$;


--
-- Name: protect_completed_evaluation_finding_actions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_completed_evaluation_finding_actions() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF OLD.status = 'completed' THEN RAISE EXCEPTION 'Completed evaluation finding actions are immutable'; END IF; RETURN NEW; END; $$;


--
-- Name: protect_decided_evaluation_finding_action_updates(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_decided_evaluation_finding_action_updates() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF OLD.status IN ('verified', 'rejected') THEN RAISE EXCEPTION 'Decided evaluation finding action updates are immutable'; END IF; RETURN NEW; END; $$;


--
-- Name: protect_decided_evaluation_finding_updates(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_decided_evaluation_finding_updates() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF OLD.status IN ('verified', 'rejected') THEN RAISE EXCEPTION 'Decided evaluation finding updates are immutable'; END IF; RETURN NEW; END; $$;


--
-- Name: protect_document_version_history(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_document_version_history() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN
    RAISE EXCEPTION 'Document version history is immutable';
END; $$;


--
-- Name: protect_final_document_extraction_attempts(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_final_document_extraction_attempts() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF TG_OP = 'DELETE' OR OLD.status <> 'processing' THEN RAISE EXCEPTION 'Completed document extraction attempts are immutable'; END IF; RETURN NEW; END; $$;


--
-- Name: protect_integration_exchange_attempts(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_integration_exchange_attempts() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Integration exchange attempts are immutable'; END; $$;


--
-- Name: protect_learning_offline_sync_evidence(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_learning_offline_sync_evidence() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF TG_OP = 'DELETE' OR OLD.status <> 'pending' THEN
        RAISE EXCEPTION 'Learning offline synchronization evidence is immutable';
    END IF;
    RETURN NEW;
END;
$$;


--
-- Name: protect_performance_test_runs(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_performance_test_runs() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Performance test evidence is immutable'; END; $$;


--
-- Name: protect_queue_recovery_attempts(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_queue_recovery_attempts() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Queue recovery attempts are immutable'; END; $$;


--
-- Name: protect_ready_learning_offline_packages(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_ready_learning_offline_packages() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.status = 'ready' THEN
        RAISE EXCEPTION 'Ready learning offline packages are immutable';
    END IF;
    RETURN COALESCE(NEW, OLD);
END;
$$;


--
-- Name: protect_released_workflow_versions(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_released_workflow_versions() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF TG_OP = 'DELETE' AND OLD.status IN ('published', 'retired') THEN
        RAISE EXCEPTION 'Released workflow versions are immutable';
    END IF;

    IF TG_OP = 'UPDATE' AND OLD.status = 'retired' THEN
        RAISE EXCEPTION 'Retired workflow versions are immutable';
    END IF;

    IF TG_OP = 'UPDATE' AND OLD.status = 'published' THEN
        IF NEW.status <> 'retired'
            OR (to_jsonb(NEW) - ARRAY['status', 'effective_to', 'updated_at'])
                IS DISTINCT FROM (to_jsonb(OLD) - ARRAY['status', 'effective_to', 'updated_at']) THEN
            RAISE EXCEPTION 'Published workflow versions may only be retired';
        END IF;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;


--
-- Name: protect_security_incident_events(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_security_incident_events() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Security incident event evidence is immutable'; END; $$;


--
-- Name: protect_supply_chain_scans(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_supply_chain_scans() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Supply-chain scan evidence is immutable'; END; $$;


--
-- Name: protect_terminal_identity_lifecycle_request(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_terminal_identity_lifecycle_request() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF OLD.status IN ('applied', 'rejected') THEN RAISE EXCEPTION 'Terminal identity lifecycle evidence is immutable'; END IF; RETURN NEW; END; $$;


--
-- Name: protect_terminal_innovation_replication(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_terminal_innovation_replication() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF OLD.status IN ('adopted', 'abandoned') THEN
        RAISE EXCEPTION 'Terminal innovation replication evidence is immutable';
    END IF;
    RETURN NEW;
END;
$$;


--
-- Name: protect_terminal_project_schedule_baseline(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_terminal_project_schedule_baseline() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN IF OLD.status IN ('approved', 'rejected') THEN RAISE EXCEPTION 'Terminal project schedule baselines are immutable'; END IF; RETURN NEW; END; $$;


--
-- Name: protect_verified_project_indicator_result(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_verified_project_indicator_result() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM project_progress_updates
        WHERE id = OLD.project_progress_update_id
          AND verification_status = 'verified'
    ) THEN
        RAISE EXCEPTION 'Results from verified project progress are immutable';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;


--
-- Name: protect_workflow_transition_history(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.protect_workflow_transition_history() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    RAISE EXCEPTION 'Workflow transition history is immutable';
END;
$$;


--
-- Name: reject_exchequer_event_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_exchequer_event_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'exchequer events are immutable'; END; $$;


--
-- Name: reject_innovation_funding_decision_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_innovation_funding_decision_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'innovation funding decisions are immutable'; END; $$;


--
-- Name: reject_innovation_panel_review_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_innovation_panel_review_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'innovation panel reviews are immutable'; END; $$;


--
-- Name: reject_partner_action_update_decision_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_partner_action_update_decision_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Partner action update decisions are immutable'; END; $$;


--
-- Name: reject_partner_agreement_change_decision_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_partner_agreement_change_decision_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Partner agreement change decisions are immutable'; END; $$;


--
-- Name: reject_partner_agreement_change_request_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_partner_agreement_change_request_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Partner agreement change requests are immutable'; END; $$;


--
-- Name: reject_partner_collaboration_action_update_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_partner_collaboration_action_update_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Partner collaboration action updates are immutable'; END; $$;


--
-- Name: reject_partner_contribution_reconciliation_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_partner_contribution_reconciliation_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Partner contribution reconciliation evidence is immutable'; END; $$;


--
-- Name: reject_partner_contribution_source_match_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_partner_contribution_source_match_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Partner contribution source matches are immutable'; END; $$;


--
-- Name: reject_performance_goal_amendment_decision_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_performance_goal_amendment_decision_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Performance goal amendment decisions are immutable'; END; $$;


--
-- Name: reject_performance_goal_amendment_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_performance_goal_amendment_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Performance goal amendment requests are immutable'; END; $$;


--
-- Name: reject_performance_goal_version_mutation(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE OR REPLACE FUNCTION public.reject_performance_goal_version_mutation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ BEGIN RAISE EXCEPTION 'Performance goal versions are immutable'; END; $$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: access_delegations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: access_review_campaigns; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: access_review_items; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: analytics_dashboards; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: analytics_widgets; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_appeals; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_attestations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_corrective_actions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_corrective_plans; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_corrective_updates; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_criteria; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_criterion_results; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_cycles; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_documents; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_findings; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_functions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_result_publications; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_scorecard_versions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_scorecards; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_standards; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessment_thematic_areas; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: assessments; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: audit_assurance_runs; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: audit_events; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: business_calendar_holidays; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: business_calendars; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: citizen_case_attachments; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: citizen_case_messages; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: citizen_cases; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: counties; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: county_grants; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: county_rollout_wave; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: county_user; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: criterion_evidence_requirements; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: data_assets; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: data_migration_batches; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: data_migration_rows; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: data_subject_requests; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: devolution_innovations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: devolution_project_county; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: devolution_project_indicator; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: devolution_projects; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: document_dispositions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: document_extraction_attempts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: document_extractions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: document_legal_holds; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: document_links; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: document_versions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_actions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_decisions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_meeting_series; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_meeting_series_user; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_meeting_user; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_meetings; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_working_group_county; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_working_group_sector; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_working_group_user; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: dswg_working_groups; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: evaluation_finding_action_updates; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: evaluation_finding_actions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: evaluation_finding_updates; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: evaluation_findings; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: exchequer_events; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: exchequer_requests; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: historical_metrics; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: identity_lifecycle_requests; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_forum_meetings; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_forums; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_gap_categories; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_resolution_assignments; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_resolution_dependencies; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_resolution_gaps; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_resolution_updates; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: igr_resolutions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: indicator_definitions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: indicator_observations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: innovation_experiment_milestones; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: innovation_funding_decisions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: innovation_panel_reviews; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: innovation_replications; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: integration_contracts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: integration_exchange_attempts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: integration_exchanges; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: integration_systems; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: knowledge_community_reports; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: knowledge_discussion_subscriptions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: knowledge_discussions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: knowledge_item_learning_course; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: knowledge_items; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: knowledge_posts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_assessment_attempts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_certificates; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_cohort_memberships; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_cohorts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_courses; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_enrollments; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_lessons; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_modules; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_offline_packages; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_offline_syncs; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_progress; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: learning_quiz_questions; Type: TABLE; Schema: public; Owner: -
--





--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: oauth_access_tokens; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: oauth_auth_codes; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: oauth_clients; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: oauth_device_codes; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: oauth_refresh_tokens; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: operational_backups; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: organizations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_agreement_change_decisions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_agreement_change_requests; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_agreements; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_collaboration_action_update_decisions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_collaboration_action_updates; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_collaboration_actions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_collaboration_alerts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_collaboration_plans; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_contribution_reconciliations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_contribution_source_matches; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_contributions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_operational_alerts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_profile_county; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_profile_sector; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_profile_user; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: partner_profiles; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: passkeys; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_cycles; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_goal_amendment_decisions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_goal_amendments; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_goal_versions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_goals; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_plans; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_reviews; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: performance_test_runs; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: platform_settings; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: privacy_incidents; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: processing_activities; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: programme_county_coverages; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: programme_evaluations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: programmes; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_budget_lines; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_indicator_results; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_milestones; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_procurements; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_progress_updates; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_resource_allocations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_resources; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_risks; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: project_schedule_baselines; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: queue_recovery_attempts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: reconciliation_exceptions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: reconciliation_runs; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: reference_data_releases; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: release_records; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: report_runs; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: report_schedules; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: retention_schedules; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: rollout_waves; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: sectors; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: security_incident_events; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: security_incidents; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: security_threats; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: service_level_measurements; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: supply_chain_scans; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: team_invitations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: team_members; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: training_assessments; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: training_cohorts; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: training_participants; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: travel_approvals; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: travel_itineraries; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: travel_requests; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: virtual_classroom_attendances; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: virtual_classrooms; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: workflow_definitions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: workflow_escalations; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: workflow_instances; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: workflow_transitions; Type: TABLE; Schema: public; Owner: -
--




--
-- Name: workflow_versions; Type: TABLE; Schema: public; Owner: -
--
SQL);
    }

    public function down(): void
    {
        DB::unprepared("DO \$\$ DECLARE item record; BEGIN FOR item IN SELECT namespace.nspname AS schema_name, procedure.proname AS function_name, pg_get_function_identity_arguments(procedure.oid) AS arguments FROM pg_proc AS procedure INNER JOIN pg_namespace AS namespace ON namespace.oid = procedure.pronamespace WHERE namespace.nspname = 'public' LOOP EXECUTE format('DROP FUNCTION IF EXISTS %I.%I(%s) CASCADE', item.schema_name, item.function_name, item.arguments); END LOOP; END \$\$;");
    }
};
