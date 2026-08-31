<?php

namespace App\Models\Concerns;

use App\Support\Database\SequenceGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

trait HasUlidAndSequence
{
    use HasUlids;

    protected static function bootHasUlidAndSequence(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('sequence') !== null) {
                return;
            }

            $sequence = resolve(SequenceGenerator::class)->next($model->getTable());

            $model->setAttribute('sequence', $sequence);
        });
    }
}
