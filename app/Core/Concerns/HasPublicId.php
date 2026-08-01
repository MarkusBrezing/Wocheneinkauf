<?php

namespace App\Core\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Vergibt beim Anlegen eine ULID in der Spalte public_id.
 *
 * Nach aussen -- in URLs und API-Antworten -- wird ausschliesslich diese ID
 * verwendet, damit fortlaufende Primaerschluessel nicht durchzaehlbar sind.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeWherePublicId(Builder $query, string $publicId): Builder
    {
        return $query->where($this->getTable().'.public_id', $publicId);
    }
}
