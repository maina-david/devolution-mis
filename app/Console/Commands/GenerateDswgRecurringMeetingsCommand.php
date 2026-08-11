<?php

namespace App\Console\Commands;

use App\Actions\GenerateDswgRecurringMeetings;
use App\Models\DswgMeetingSeries;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dswg:generate-recurring-meetings')]
#[Description('Generate the governed rolling horizon of DSWG recurring meetings')]
class GenerateDswgRecurringMeetingsCommand extends Command
{
    public function handle(GenerateDswgRecurringMeetings $generateMeetings): int
    {
        $generated = 0;
        DswgMeetingSeries::query()
            ->where('status', 'active')
            ->with('creator')
            ->orderBy('id')
            ->chunkById(50, function ($seriesCollection) use ($generateMeetings, &$generated): void {
                foreach ($seriesCollection as $series) {
                    $generated += $generateMeetings->handle($series, $series->creator);
                }
            });

        $this->components->info("Generated {$generated} recurring DSWG meeting(s).");

        return self::SUCCESS;
    }
}
