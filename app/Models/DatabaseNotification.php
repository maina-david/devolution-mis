<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

#[Fillable(['id', 'type', 'data', 'read_at'])]
class DatabaseNotification extends BaseDatabaseNotification
{
    use HasUuids, SoftDeletes;
}
