---
paths:
  - config/filesystems.php
---

# Config

## Production /storage URLs need a hand-made symlink, never an .htaccess rewrite
The `public` disk publishes media at `APP_URL.'/storage'`, which resolves only because `public/storage` symlinks to `storage/app/public`. On shared hosting (utuntutwubwenge.co.rw, Hostinger) `php artisan storage:link` cannot create it: PHP's `symlink()` is in the host's `disable_functions`. Create it over SSH instead — `ln -sfn ../storage/app/public public/storage` — and recreate it after any deploy that replaces the checkout.

Do not "fix" it with a rewrite of the form `RewriteRule ^storage/(.*)$ storage/app/public/$1`. The substitution self-matches the same pattern, so mod_rewrite loops to Apache's internal-redirect limit and every media URL returns 500 rather than 404. If a rewrite is unavoidable, the target must not match `^storage/` (the sibling manornesthomes.com site maps onto a `media/` symlink for exactly this reason).

Also note `/storage/{path}` is a real route (`storage.local`, from the local disk's `'serve' => true`) serving `storage/app/private` — a different directory from the `public` disk. It only ever answers when the symlink misses, so a broken symlink shows up as 404s from Laravel rather than as missing files.
