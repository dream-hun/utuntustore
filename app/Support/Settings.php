<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;

/**
 * Runtime platform configuration an admin can change without a deployment.
 *
 * Values fall back to config/marketplace.php when unset, so a fresh install works
 * before anyone has touched the settings screen. The whole table is cached as one
 * entry — it is a handful of rows read on nearly every request, and a single
 * forget() on write keeps it consistent.
 */
final class Settings
{
    private const string CACHE_KEY = 'marketplace.settings';

    private const int CACHE_TTL = 3600;

    /** @var array<string, string|null>|null */
    private ?array $loaded = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly CacheRepository $cache,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->all()[$key] ?? null;

        return $value === null ? $default : (int) $value;
    }

    public function string(string $key, string $default = ''): string
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, string|int|null $value): void
    {
        $this->connection->table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'updated_at' => now(), 'created_at' => now()],
        );

        $this->flush();
    }

    /**
     * @param  array<string, string|int|null>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        /** @var array<string, string|null> $settings */
        $settings = $this->cache->remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn (): array => $this->connection->table('settings')
                ->pluck('value', 'key')
                ->all(),
        );

        return $this->loaded = $settings;
    }

    public function flush(): void
    {
        $this->loaded = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * The annual vendor subscription fee, in whole RWF.
     */
    public function subscriptionFee(): int
    {
        return $this->integer('vendor_subscription_fee', Config::integer('marketplace.subscription.fee'));
    }

    public function subscriptionCurrency(): string
    {
        return $this->string('vendor_subscription_currency', Config::string('marketplace.currency'));
    }

    /**
     * How long a paid subscription period lasts.
     */
    public function subscriptionDays(): int
    {
        return $this->integer('vendor_subscription_days', Config::integer('marketplace.subscription.days'));
    }

    /**
     * How long an expired vendor keeps selling rights before the shop is hidden.
     */
    public function subscriptionGraceDays(): int
    {
        return $this->integer('vendor_subscription_grace_days', Config::integer('marketplace.subscription.grace_days'));
    }
}
