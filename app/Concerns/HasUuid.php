<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives a model a public UUID and routes on it instead of the auto-increment id.
 *
 * Sequential ids stay internal so URLs never leak how many vendors, orders or
 * customers the marketplace has.
 */
trait HasUuid
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }
}
