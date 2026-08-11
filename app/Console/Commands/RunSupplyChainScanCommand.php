<?php

namespace App\Console\Commands;

use App\Actions\RunSupplyChainScan;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('security:supply-chain-scan {--user= : Optional initiating user UUID}')]
#[Description('Generate a lock-derived CycloneDX SBOM and retain immutable dependency-audit evidence')]
class RunSupplyChainScanCommand extends Command
{
    public function handle(RunSupplyChainScan $action): int
    {
        $userId = $this->option('user');
        $user = is_string($userId) && $userId !== '' ? User::query()->find($userId) : null;

        if (is_string($userId) && $userId !== '' && $user === null) {
            $this->error('The initiating user UUID does not exist.');

            return self::INVALID;
        }

        $scan = $action->handle($user);
        $this->table(
            ['Evidence', 'Outcome', 'Composer components', 'JavaScript components', 'High', 'Critical', 'Checksum'],
            [[$scan->id, $scan->outcome, $scan->composer_component_count, $scan->javascript_component_count, $scan->npm_high_count, $scan->npm_critical_count, $scan->evidence_checksum]],
        );

        if ($scan->outcome === 'warn') {
            $this->warn('Evidence was retained with findings that require review.');
        }

        return $scan->outcome === 'fail' ? self::FAILURE : self::SUCCESS;
    }
}
