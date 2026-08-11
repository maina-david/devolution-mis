<?php

namespace App\Models;

use Database\Factories\ServiceLevelMeasurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $observed_at */
#[Fillable(['service', 'metric', 'value', 'unit', 'target', 'status', 'observed_at', 'metadata'])]
class ServiceLevelMeasurement extends Model
{
    /** @use HasFactory<ServiceLevelMeasurementFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['observed_at' => 'immutable_datetime', 'metadata' => 'array'];
    }
}
