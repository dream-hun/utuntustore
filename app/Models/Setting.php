<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Platform configuration an admin can change without a deployment: the subscription
 * fee, period length and grace period.
 *
 * Settings are keyed strings addressed by an admin UI and never exposed on a public
 * URL, so this model deliberately has no uuid and keeps the default id route key.
 *
 * @property-read int $id
 * @property string $key
 * @property string|null $value
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 */
final class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    public $timestamps = true;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [];
    }
}
