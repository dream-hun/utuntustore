<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

/**
 * Return a seeder's PHP source with every comment stripped.
 *
 * The factory guard below searches for a `factory(` call, and the seeders carry
 * prose explaining why they must not make one. Matching raw source would fire on
 * that prose, so the comments are dropped through the tokenizer rather than by a
 * regex that would have to understand strings and heredocs.
 */
function seederCode(string $seeder): string
{
    $file = (new ReflectionClass($seeder))->getFileName();

    expect($file)->toBeString();

    $code = '';

    foreach (token_get_all((string) file_get_contents((string) $file)) as $token) {
        if (! is_array($token)) {
            $code .= $token;

            continue;
        }

        if ($token[0] === T_COMMENT) {
            continue;
        }

        if ($token[0] === T_DOC_COMMENT) {
            continue;
        }

        $code .= $token[1];
    }

    return $code;
}

/**
 * Every seeder DatabaseSeeder runs, plus DatabaseSeeder itself — the exact set a
 * production `db:seed` executes. Read off `run()` rather than hardcoded, so a
 * seeder added to the production path is covered by the factory guard on the day
 * it is added rather than the day someone remembers to update this list.
 *
 * @return list<class-string>
 */
function productionSeedPath(): array
{
    preg_match_all('/(\w+Seeder)::class/', seederCode(DatabaseSeeder::class), $matches);

    $seeders = [DatabaseSeeder::class];

    foreach ($matches[1] as $name) {
        $seeders[] = 'Database\\Seeders\\'.$name;
    }

    return $seeders;
}

/**
 * A fresh production install has no other way into the admin area, so this is the
 * one seeder path a real deployment depends on working.
 */
it('seeds a working admin login on a fresh install', function (): void {
    $this->seed(DatabaseSeeder::class);

    $admin = User::query()->where('email', Config::string('marketplace.admin.email'))->first();

    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check(Config::string('marketplace.admin.password'), (string) $admin->password))->toBeTrue();
});

/**
 * Re-seeding is how reference data gets corrected in place, so it has to stay safe
 * to run against a live database rather than duplicating the admin.
 */
it('leaves the existing admin untouched when seeded again', function (): void {
    $this->seed(DatabaseSeeder::class);

    $created = User::query()->where('email', Config::string('marketplace.admin.email'))->sole();

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', Config::string('marketplace.admin.email'))->count())->toBe(1)
        ->and(User::query()->sole()->uuid)->toBe($created->uuid);
});

/**
 * The trap this guards, which cost a production deploy on 2026-08-18:
 *
 * `User::factory()` reaches UserFactory::definition(), which calls `fake()`. That
 * function ships in `fakerphp/faker`, a require-dev package, so it is absent from
 * every `composer install --no-dev` install. A factory anywhere on the production
 * seed path is therefore fatal on a real host with
 * `Call to undefined function Database\Factories\fake()` — and it fails *after*
 * the reference-data seeders have committed, leaving a half-seeded database with
 * no admin account and no way in.
 *
 * Faker is installed when this suite runs, so nothing here would notice the
 * regression at runtime; the guard has to be made against the source.
 *
 * DemoDataSeeder uses factories freely and is deliberately absent from this set —
 * it is never called by DatabaseSeeder, which is exactly what keeps it safe.
 */
it('keeps the production seed path free of factories', function (string $seeder): void {
    expect(seederCode($seeder))
        ->not->toContain('factory(')
        ->not->toContain('fake(');
})->with(productionSeedPath());
