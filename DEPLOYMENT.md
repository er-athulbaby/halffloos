# Deploying Halffloos

Three things in this repository behave differently in production than they do on
a developer's machine. Each one fails quietly if it is missed.

## 1. SQLite in development, MySQL in production

Development uses SQLite: a single file, no server to install, and Laravel's
default. Production uses MySQL. Switching is a `.env` change, with one exception
below.

### The 50% floor must be re-expressed before the first MySQL deploy

The 50% rule — `price_fils * 2 <= retail_value_fils` — is enforced in two places
on purpose: the `AtLeastHalfOff` validation rule, and the database itself, so
that no future controller, console command or manual query can quietly list
something at 10% off.

The database half of that is implemented in
`database/migrations/2026_09_17_190336_create_halffloos_tables.php` as two
SQLite triggers using `RAISE(ABORT, ...)`. **That syntax is SQLite-only.**
`php artisan migrate` against MySQL fails on it.

The failure looks like a syntax error, and the obvious fix — deleting the
triggers to get the migration through — silently removes the floor from the
database and leaves the whole rule resting on one validation class. Do not do
that. Replace them instead:

```sql
ALTER TABLE offers
  ADD CONSTRAINT offers_half_off
  CHECK (price_fils * 2 <= retail_value_fils);
```

Requires **MySQL 8.0.16 or later** — before that version `CHECK` constraints
parse but are not enforced, which is worse than having none. Verify with
`SELECT VERSION();` before deploying.

The constraint must accept exactly half: BHD 1.000 against BHD 2.000 is a valid
listing. `<=`, not `<`. `tests/Feature/SchemaTest.php` asserts that boundary.

## 2. The scheduler must be running

`halffloos:close-expired` closes offers whose pickup window has passed and marks
uncollected reservations as no-shows. It is registered in `routes/console.php`
to run every five minutes.

Nothing else closes an offer. If the scheduler is not running, expired offers
stay `active` and remain reservable indefinitely — customers turn up at shops
that closed hours ago.

Add one cron entry on the production host:

```cron
* * * * * cd /path/to/halffloos && php artisan schedule:run >> /dev/null 2>&1
```

The command waits `GRACE_MINUTES` (30) past `pickup_end` before marking anyone a
no-show, so a customer still queuing at the till is not struck. Two no-shows
block an account for seven days and there is no admin screen to reverse it, so
do not shorten that grace period without building one.

## 3. No `gd`, `imagick` or `intl` in this PHP build

PHP is installed via `php.new` (`~/.config/herd-lite/bin`) as a static binary
with no extensions directory. These three cannot be enabled. Consequences:

- **Images are resized in the browser before upload.** There is no server-side
  image processing and adding it would mean changing the PHP build. This is the
  better design regardless — nobody should push a 4 MB phone photo over Bahraini
  mobile data — but it means the client-side resize is load-bearing, not a
  nicety. Enforce a maximum upload size server-side as well.
- **BHD is formatted by hand** from integer fils in `App\Support\Money::format()`,
  not through `NumberFormatter`.

If the production host uses a different PHP build that does have these
extensions, nothing breaks — but do not add server-side image processing on the
assumption that every environment has it.

## HTTPS is required for barcode scanning

The merchant listing screen scans barcodes through `getUserMedia`, which browsers
only expose on a secure origin. `localhost` counts as secure, so development works
over plain HTTP — production does not.

Served over HTTP, the camera request fails and the screen falls back to its "No
camera available. Type the details instead." message. That is a graceful failure,
not a visible error, so it will not show up in logs or error tracking. If merchants
report that scanning "does nothing", check the certificate first.

The scanner uses the browser's own `BarcodeDetector` where it exists (Chrome on
Android) and a ZXing WebAssembly fallback elsewhere (Safari, iOS). The wasm is only
downloaded on browsers that need it.
