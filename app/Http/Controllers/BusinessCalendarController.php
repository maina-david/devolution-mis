<?php

namespace App\Http\Controllers;

use App\Actions\PublishBusinessCalendar;
use App\Enums\ProgrammePermission;
use App\Http\Requests\StoreBusinessCalendarHolidayRequest;
use App\Http\Requests\StoreBusinessCalendarRequest;
use App\Models\BusinessCalendar;
use App\Models\BusinessCalendarHoliday;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BusinessCalendarController extends Controller
{
    public function store(StoreBusinessCalendarRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        $calendar = DB::transaction(function () use ($request, $user): BusinessCalendar {
            $code = $request->string('code')->upper()->toString();
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["business-calendar:{$code}"]);

            return BusinessCalendar::create([...$request->validated(), 'code' => $code, 'version' => ((int) BusinessCalendar::query()->withTrashed()->where('code', $code)->max('version')) + 1, 'created_by' => $user->id, 'status' => 'draft']);
        }, attempts: 3);
        $auditLogger->record($user, $calendar, 'workflow.business-calendar.created', "Business calendar {$calendar->code} v{$calendar->version} drafted.");

        return back()->with('success', 'Business calendar draft created.');
    }

    public function storeHoliday(StoreBusinessCalendarHolidayRequest $request, BusinessCalendar $businessCalendar, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($businessCalendar->status === 'draft', 409, 'Published calendars are immutable. Create a new version to amend holidays.');
        $user = $this->user($request);
        $holiday = $businessCalendar->holidays()->create([...$request->validated(), 'created_by' => $user->id]);
        $auditLogger->record($user, $holiday, 'workflow.business-calendar.holiday-added', "{$holiday->name} added to {$businessCalendar->code} v{$businessCalendar->version}.");

        return back()->with('success', 'Calendar exception added.');
    }

    public function destroyHoliday(Request $request, BusinessCalendar $businessCalendar, BusinessCalendarHoliday $businessCalendarHoliday, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageWorkflows->value);
        abort_unless($businessCalendarHoliday->business_calendar_id === $businessCalendar->id, 404);
        abort_unless($businessCalendar->status === 'draft', 409, 'Published calendars are immutable.');
        $user = $this->user($request);
        $auditLogger->record($user, $businessCalendarHoliday, 'workflow.business-calendar.holiday-removed', "{$businessCalendarHoliday->name} removed from draft calendar.");
        $businessCalendarHoliday->delete();

        return back()->with('success', 'Draft calendar exception removed.');
    }

    public function publish(Request $request, BusinessCalendar $businessCalendar, PublishBusinessCalendar $action): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageWorkflows->value);
        $action->handle($businessCalendar, $this->user($request));

        return back()->with('success', 'Business calendar independently published.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
