<?php

namespace App\Console\Commands;

use App\Actions\RunAuditAssurance;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:assure {--user= : Optional initiating user UUID}')]
#[Description('Verify and retain checksum-bound audit-chain assurance evidence')]
class RunAuditAssuranceCommand extends Command
{
    public function handle(RunAuditAssurance $action): int
    {
        $userId = $this->option('user');
        $user = is_string($userId) && $userId !== '' ? User::query()->find($userId) : null;
        if (is_string($userId) && $userId !== '' && $user === null) {
            $this->error('The initiating user UUID does not exist.');

            return self::INVALID;
        }
        $run = $action->handle($user);
        $this->table(['Evidence', 'Outcome', 'Events', 'Verified', 'Legacy', 'Mismatches', 'Checksum'], [[$run->id, $run->outcome, $run->event_count, $run->verified_event_count, $run->legacy_event_count, $run->mismatch_count, $run->evidence_checksum]]);
        if ($run->outcome === 'warn') {
            $this->warn('Evidence was retained with findings requiring review.');
        }

        return $run->outcome === 'fail' ? self::FAILURE : self::SUCCESS;
    }
}
