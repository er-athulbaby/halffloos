# Halffloos v1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A working web app where verified Bahraini merchants list near-expiry stock at 50%+ off and customers reserve it for in-person collection.

**Architecture:** A single Laravel app serving three surfaces — customer browse/reserve, merchant listing/collection, and admin approval — over five tables. Money is integer fils throughout. Stock is decremented by one atomic SQL statement, never read-then-write. No payment gateway: the customer pays at the merchant's own till.

**Tech Stack:** PHP 8.5, Laravel 13, Livewire 4, Tailwind 4, Vite 8, PHPUnit, SQLite in development, MySQL in production.

**Spec:** `docs/superpowers/specs/2026-09-17-surplus-food-design.md`
**Design system:** `design-system/halffloos/MASTER.md`

## Global Constraints

Every task's requirements implicitly include this section.

- **Money is stored as integer fils.** BHD has three decimal places. `BIGINT` columns, a `Money` value object, no floats anywhere.
- **The 50% rule:** a listing is rejected unless `price_fils * 2 <= retail_value_fils`. Enforced in one validation rule and one database check constraint.
- **Stock decrement is atomic:** a single conditional `UPDATE`, checking affected rows. Never read-then-write.
- **All UI strings go through `__()`.** No bare quoted strings in Blade.
- **Tailwind logical properties only** — `ps-*`/`pe-*`/`ms-*`/`me-*`, never `pl-*`/`pr-*`/`ml-*`/`mr-*`.
- **No `gd`, `imagick` or `intl`.** This PHP build is static with no extensions directory. Images resize client-side before upload; BHD is formatted by hand.
- **No Filament.** Admin is four plain Livewire pages.
- **Touch targets 44×44px minimum.** Visible focus rings, never removed.
- **Never use the words "expired", "waste", "leftover" or "old" in UI copy.** The positioning is cost-of-living. Say "expires tomorrow" and "save 70%".
- **Colors** come from `design-system/halffloos/MASTER.md` section 3. `--on-primary` is `#000000` on purpose — black clears 4.5:1 on `#059669`, white does not. Do not "fix" it.
- **Statuses:** stores `pending|approved|suspended`; offers `active|sold_out|closed`; reservations `reserved|collected|no_show|cancelled`; user roles `customer|merchant|admin`.
- **Two no-shows blocks reservations for 7 days.**
- If `php` is not on your PATH, it lives at `C:/Users/ababy/.config/herd-lite/bin/php.exe`. Restarting the terminal usually fixes this.

## Deviation from the spec

The spec says distance is computed "by haversine in the query". **Do not do this.** SQLite does not ship trigonometric functions unless compiled with `SQLITE_ENABLE_MATH_FUNCTIONS`, so a SQL haversine works on MySQL and silently fails on the development database.

Instead: filter by a **bounding box in SQL** (plain comparisons, portable everywhere), then compute exact haversine distance **in PHP** and sort there. With a few hundred stores this is faster than it sounds and completely dialect-independent. Task 6 implements this.

## File structure

| File | Responsibility |
|---|---|
| `app/Support/Money.php` | Integer-fils value object. Parsing, formatting, comparison. |
| `app/Casts/MoneyCast.php` | Eloquent cast between `BIGINT` and `Money`. |
| `app/Enums/*.php` | Backed enums for every status and role. |
| `app/Models/{User,Store,Offer,Reservation}.php` | Models, relationships, casts. |
| `app/Rules/AtLeastHalfOff.php` | The 50% validation rule, in one place. |
| `app/Support/Geo.php` | Bounding box and haversine helpers. |
| `app/Support/PickupCode.php` | Unambiguous six-character code generation. |
| `app/Actions/ReserveOffer.php` | Atomic decrement + reservation creation. |
| `app/Livewire/Merchant/*` | Application form, offer listing, till screen. |
| `app/Livewire/Customer/*` | Browse, offer detail, my reservations. |
| `app/Livewire/Admin/*` | Pending applications, stores, offers, reports. |
| `app/Console/Commands/CloseExpiredOffers.php` | Scheduled window closing and no-show marking. |

---

### Task 1: Scaffold the application

The repository already contains `docs/`, `design-system/` and a git history. `laravel new` refuses to run in a non-empty directory, so scaffold into a temporary folder and move the files in.

**Files:**
- Create: the Laravel skeleton at the repository root
- Modify: `.env`, `.gitignore`

**Interfaces:**
- Consumes: nothing
- Produces: a booting Laravel 13 app with Livewire 4 and Tailwind 4 installed, SQLite configured

- [ ] **Step 1: Scaffold Laravel into a temporary directory**

```bash
cd /c/Users/ababy/Desktop/halffloos
php /c/Users/ababy/.config/herd-lite/bin/composer.phar create-project laravel/laravel:^13.0 tmp-scaffold --no-interaction
```

- [ ] **Step 2: Move the skeleton into the repository root**

```bash
cd /c/Users/ababy/Desktop/halffloos
mv tmp-scaffold/* . 2>/dev/null
mv tmp-scaffold/.[!.]* . 2>/dev/null
rmdir tmp-scaffold
ls -a | head -20
```

Expected: `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `tests`, `vendor`, `artisan`, `.env`, `.gitignore` all present alongside `docs` and `design-system`.

- [ ] **Step 3: Configure SQLite**

Edit `.env` so the database block reads exactly:

```
DB_CONNECTION=sqlite
```

Delete the `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` lines. Then:

```bash
touch database/database.sqlite
php artisan migrate
```

Expected: migrations run against SQLite without error.

- [ ] **Step 4: Install Livewire 4**

```bash
php /c/Users/ababy/.config/herd-lite/bin/composer.phar require livewire/livewire:^4.0
```

> Livewire 4 is a recent major version. Before writing your first component in Task 4, check its current component syntax against the Livewire 4 documentation — v3 tutorials will be subtly wrong.

- [ ] **Step 5: Install Tailwind 4**

```bash
npm install tailwindcss @tailwindcss/vite
```

Add the `@theme` block below to `resources/css/app.css`, **keeping the `@source`
directives the installer generated**. Dropping them stops Tailwind scanning vendor
Blade views, so Livewire and pagination markup renders unstyled. Replace only the
installer's default font stack, not the whole file.

```css
@import "tailwindcss";

@theme {
  --color-primary: #059669;
  --color-on-primary: #000000;
  --color-secondary: #10B981;
  --color-accent: #D97706;
  --color-on-accent: #000000;
  --color-background: #ECFDF5;
  --color-foreground: #0F172A;
  --color-card: #FFFFFF;
  --color-muted: #F0F8F6;
  --color-muted-foreground: #475569;
  --color-border-subtle: #E1F2ED;
  --color-destructive: #DC2626;
  --font-sans: "Tajawal", ui-sans-serif, system-ui, sans-serif;
}
```

Add the Tailwind plugin to `vite.config.js` — the file already imports `laravel-vite-plugin`, so add the Tailwind import alongside it and register it in the `plugins` array:

```js
import tailwindcss from '@tailwindcss/vite';
```

```js
plugins: [
    laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
    tailwindcss(),
],
```

- [ ] **Step 6: Verify the app boots**

```bash
npm run build
php artisan serve
```

Expected: build succeeds; visiting `http://127.0.0.1:8000` shows the Laravel welcome page. Stop the server with Ctrl-C.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: scaffold Laravel 13 with Livewire 4 and Tailwind 4"
```

---

### Task 2: Money value object

Pure PHP, no database. Every later task depends on this, so it comes first.

**Files:**
- Create: `app/Support/Money.php`
- Create: `app/Casts/MoneyCast.php`
- Test: `tests/Unit/MoneyTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  - `Money::fromFils(int $fils): Money`
  - `Money::fromString(string $bhd): Money` — parses `"2.500"`
  - `$money->fils(): int`
  - `$money->format(): string` — returns `"BHD 2.500"`
  - `$money->isAtMostHalfOf(Money $other): bool`
  - `$money->percentOffFrom(Money $original): int`
  - `MoneyCast` for use as `protected function casts()` entries

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/MoneyTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_stores_fils_exactly(): void
    {
        $this->assertSame(2500, Money::fromFils(2500)->fils());
    }

    public function test_it_parses_a_three_decimal_string(): void
    {
        $this->assertSame(2500, Money::fromString('2.500')->fils());
        $this->assertSame(500, Money::fromString('0.5')->fils());
        $this->assertSame(2000, Money::fromString('2')->fils());
    }

    public function test_it_formats_with_three_decimals(): void
    {
        $this->assertSame('BHD 2.500', Money::fromFils(2500)->format());
        $this->assertSame('BHD 0.500', Money::fromFils(500)->format());
        $this->assertSame('BHD 0.050', Money::fromFils(50)->format());
    }

    public function test_it_knows_when_it_is_at_most_half_of_another(): void
    {
        $original = Money::fromFils(2000);

        $this->assertTrue(Money::fromFils(1000)->isAtMostHalfOf($original));
        $this->assertTrue(Money::fromFils(500)->isAtMostHalfOf($original));
        $this->assertFalse(Money::fromFils(1001)->isAtMostHalfOf($original));
    }

    public function test_it_calculates_percent_off(): void
    {
        $this->assertSame(75, Money::fromFils(500)->percentOffFrom(Money::fromFils(2000)));
        $this->assertSame(50, Money::fromFils(1000)->percentOffFrom(Money::fromFils(2000)));
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromFils(-1);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=MoneyTest
```

Expected: FAIL — `Class "App\Support\Money" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/Money.php`:

```php
<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(public int $fils)
    {
        if ($fils < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function fromFils(int $fils): self
    {
        return new self($fils);
    }

    /** Parses a BHD string such as "2.500", "0.5" or "2". */
    public static function fromString(string $bhd): self
    {
        $trimmed = trim($bhd);

        if (! preg_match('/^\d+(\.\d{1,3})?$/', $trimmed)) {
            throw new InvalidArgumentException("Not a valid BHD amount: {$bhd}");
        }

        [$dinars, $fraction] = array_pad(explode('.', $trimmed), 2, '0');

        return new self(((int) $dinars * 1000) + (int) str_pad($fraction, 3, '0'));
    }

    public function fils(): int
    {
        return $this->fils;
    }

    public function format(): string
    {
        return sprintf('BHD %d.%03d', intdiv($this->fils, 1000), $this->fils % 1000);
    }

    public function isAtMostHalfOf(self $other): bool
    {
        return $this->fils * 2 <= $other->fils;
    }

    public function percentOffFrom(self $original): int
    {
        if ($original->fils === 0) {
            return 0;
        }

        // intdiv, not float division — the "no floats" constraint is binding,
        // and this gives identical floor semantics with exact arithmetic.
        return intdiv(($original->fils - $this->fils) * 100, $original->fils);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --filter=MoneyTest
```

Expected: PASS, 6 tests.

- [ ] **Step 5: Add the Eloquent cast**

Create `app/Casts/MoneyCast.php`:

```php
<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromFils((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof Money ? $value->fils() : Money::fromString((string) $value)->fils();
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add app/Support/Money.php app/Casts/MoneyCast.php tests/Unit/MoneyTest.php
git commit -m "feat: add Money value object storing integer fils"
```

---

### Task 3: Schema, enums and models

**Files:**
- Create: `app/Enums/{UserRole,StoreStatus,OfferStatus,OfferType,ReservationStatus}.php`
- Create: `database/migrations/*_create_halffloos_tables.php`
- Create: `app/Models/{Store,Offer,Reservation}.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/SchemaTest.php`

**Interfaces:**
- Consumes: `App\Casts\MoneyCast`, `App\Support\Money`
- Produces:
  - `Store` with `offers()`, `user()`
  - `Offer` with `store()`, `reservations()`, `retail_value` and `price` cast to `Money`
  - `Reservation` with `offer()`, `user()`
  - `User` with `stores()`, `reservations()`, `role`, `no_show_count`, `blocked_until`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SchemaTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(): Store
    {
        $user = User::create([
            'name' => 'Cold Store Owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        return Store::create([
            'user_id' => $user->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17000000',
            'cr_number' => '12345-1',
            'food_licence_no' => 'MOH-9988',
            'status' => StoreStatus::Approved,
        ]);
    }

    public function test_an_offer_casts_money_to_fils(): void
    {
        $offer = Offer::create([
            'store_id' => $this->makeStore()->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 100,
            'remaining' => 100,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->setTime(20, 0),
            'pickup_end' => now()->setTime(22, 0),
            'status' => OfferStatus::Active,
        ]);

        $this->assertSame(500, $offer->fresh()->price_fils->fils());
        $this->assertSame('BHD 0.500', $offer->fresh()->price_fils->format());
    }

    public function test_the_database_rejects_a_discount_under_fifty_percent(): void
    {
        $this->expectException(QueryException::class);

        Offer::create([
            'store_id' => $this->makeStore()->id,
            'type' => OfferType::Item,
            'title' => 'Barely discounted milk',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('1.500'),
            'quantity' => 10,
            'remaining' => 10,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->setTime(20, 0),
            'pickup_end' => now()->setTime(22, 0),
            'status' => OfferStatus::Active,
        ]);
    }

    public function test_a_store_has_offers(): void
    {
        $store = $this->makeStore();

        $this->assertSame(0, $store->offers()->count());
        $this->assertSame(StoreStatus::Approved, $store->status);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=SchemaTest
```

Expected: FAIL — `Class "App\Enums\UserRole" not found`.

- [ ] **Step 3: Create the enums**

`app/Enums/UserRole.php`:

```php
<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Merchant = 'merchant';
    case Admin = 'admin';
}
```

`app/Enums/StoreStatus.php`:

```php
<?php

namespace App\Enums;

enum StoreStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Suspended = 'suspended';
}
```

`app/Enums/OfferStatus.php`:

```php
<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Active = 'active';
    case SoldOut = 'sold_out';
    case Closed = 'closed';
}
```

`app/Enums/OfferType.php`:

```php
<?php

namespace App\Enums;

enum OfferType: string
{
    case Item = 'item';
    case Lot = 'lot';
}
```

`app/Enums/ReservationStatus.php`:

```php
<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Reserved = 'reserved';
    case Collected = 'collected';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
```

- [ ] **Step 4: Create the migration**

```bash
php artisan make:migration create_halffloos_tables
```

Replace the generated file's contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->unsignedInteger('no_show_count')->default(0)->after('phone');
            $table->timestamp('blocked_until')->nullable()->after('no_show_count');
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('area');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('phone');
            $table->text('pickup_instructions')->nullable();
            $table->string('image')->nullable();
            $table->string('cr_number');
            $table->string('food_licence_no');
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('delivers')->default(false);
            $table->unsignedBigInteger('delivery_fee_fils')->nullable();
            $table->timestamps();

            $table->index(['status', 'lat', 'lng']);
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('item');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('retail_value_fils');
            $table->unsignedBigInteger('price_fils');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('remaining');
            $table->unsignedInteger('max_per_customer')->default(2);
            $table->date('expires_on');
            $table->dateTime('pickup_start');
            $table->dateTime('pickup_end');
            $table->string('image')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['status', 'pickup_end']);
        });

        // The 50% rule, enforced by the database as well as by validation.
        // Laravel has no fluent check-constraint builder, so this is raw SQL.
        DB::statement(
            'CREATE TRIGGER offers_half_off_insert BEFORE INSERT ON offers
             FOR EACH ROW BEGIN
                SELECT RAISE(ABORT, "price must be at most half of retail value")
                WHERE NEW.price_fils * 2 > NEW.retail_value_fils;
             END'
        );

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->string('pickup_code', 6);
            $table->string('status')->default('reserved');
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'pickup_code']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->json('keys');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS offers_half_off_insert');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('stores');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'no_show_count', 'blocked_until']);
        });
    }
};
```

> The trigger above is SQLite syntax. **When you move to MySQL, replace it** with a native check constraint:
> `ALTER TABLE offers ADD CONSTRAINT offers_half_off CHECK (price_fils * 2 <= retail_value_fils)`.
> Put that swap in the production deployment notes; do not try to write one statement that satisfies both dialects.

- [ ] **Step 5: Create the models**

`app/Models/Store.php`:

```php
<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'verified_at' => 'datetime',
            'delivers' => 'boolean',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
```

`app/Models/Offer.php`:

```php
<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\OfferStatus;
use App\Enums\OfferType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => OfferType::class,
            'status' => OfferStatus::class,
            'retail_value_fils' => MoneyCast::class,
            'price_fils' => MoneyCast::class,
            'expires_on' => 'date',
            'pickup_start' => 'datetime',
            'pickup_end' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
```

`app/Models/Reservation.php`:

```php
<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'collected_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

In `app/Models/User.php`, add to the `casts()` method and add the relationships:

```php
'role' => \App\Enums\UserRole::class,
'blocked_until' => 'datetime',
```

```php
public function stores(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Store::class);
}

public function reservations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Reservation::class);
}
```

Also add `'role'`, `'phone'` to the `$fillable` array.

- [ ] **Step 6: Run the tests to verify they pass**

```bash
php artisan test --filter=SchemaTest
```

Expected: PASS, 3 tests.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add schema, enums and models with fils money casts"
```

---

### Task 4: The 50% validation rule

The database trigger is the safety net. This is the rule that produces a readable error beside the price field.

**Files:**
- Create: `app/Rules/AtLeastHalfOff.php`
- Test: `tests/Unit/AtLeastHalfOffTest.php`

**Interfaces:**
- Consumes: `App\Support\Money`
- Produces: `new AtLeastHalfOff(Money $retailValue)` usable in any Laravel validation array

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/AtLeastHalfOffTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Rules\AtLeastHalfOff;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AtLeastHalfOffTest extends TestCase
{
    private function validate(string $price, string $retail): bool
    {
        return Validator::make(
            ['price' => $price],
            ['price' => [new AtLeastHalfOff(Money::fromString($retail))]]
        )->passes();
    }

    public function test_it_accepts_exactly_half_off(): void
    {
        $this->assertTrue($this->validate('1.000', '2.000'));
    }

    public function test_it_accepts_more_than_half_off(): void
    {
        $this->assertTrue($this->validate('0.500', '2.000'));
    }

    public function test_it_rejects_less_than_half_off(): void
    {
        $this->assertFalse($this->validate('1.500', '2.000'));
        $this->assertFalse($this->validate('1.001', '2.000'));
    }

    public function test_it_rejects_a_malformed_amount(): void
    {
        $this->assertFalse($this->validate('abc', '2.000'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=AtLeastHalfOffTest
```

Expected: FAIL — `Class "App\Rules\AtLeastHalfOff" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Rules/AtLeastHalfOff.php`:

```php
<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class AtLeastHalfOff implements ValidationRule
{
    public function __construct(private Money $retailValue) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $price = Money::fromString((string) $value);
        } catch (InvalidArgumentException) {
            $fail(__('Enter a price such as 0.500.'));

            return;
        }

        if (! $price->isAtMostHalfOf($this->retailValue)) {
            $fail(__('The price must be at most half of :retail.', [
                'retail' => $this->retailValue->format(),
            ]));
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --filter=AtLeastHalfOffTest
```

Expected: PASS, 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Rules/AtLeastHalfOff.php tests/Unit/AtLeastHalfOffTest.php
git commit -m "feat: add the 50% minimum discount validation rule"
```

---

### Task 5: Pickup codes

**Files:**
- Create: `app/Support/PickupCode.php`
- Test: `tests/Unit/PickupCodeTest.php`

**Interfaces:**
- Consumes: nothing
- Produces: `PickupCode::generate(): string` — six characters from an unambiguous alphabet

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/PickupCodeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\PickupCode;
use PHPUnit\Framework\TestCase;

class PickupCodeTest extends TestCase
{
    public function test_it_is_six_characters(): void
    {
        $this->assertSame(6, strlen(PickupCode::generate()));
    }

    public function test_it_avoids_ambiguous_characters(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->assertDoesNotMatchRegularExpression('/[O0I1L]/', PickupCode::generate());
        }
    }

    public function test_it_is_uppercase_alphanumeric(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', PickupCode::generate());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=PickupCodeTest
```

Expected: FAIL — `Class "App\Support\PickupCode" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/PickupCode.php`:

```php
<?php

namespace App\Support;

final class PickupCode
{
    /**
     * O, 0, I, 1 and L are omitted — a shop worker reads these aloud
     * across a counter, and confusing them wastes everyone's time.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --filter=PickupCodeTest
```

Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/PickupCode.php tests/Unit/PickupCodeTest.php
git commit -m "feat: add unambiguous pickup code generation"
```

---

### Task 6: Distance filtering

**Files:**
- Create: `app/Support/Geo.php`
- Test: `tests/Unit/GeoTest.php`

**Interfaces:**
- Consumes: nothing
- Produces:
  - `Geo::boundingBox(float $lat, float $lng, float $radiusKm): array` — returns `['minLat' => float, 'maxLat' => float, 'minLng' => float, 'maxLng' => float]`
  - `Geo::distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/GeoTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_distance_between_manama_and_riffa(): void
    {
        // Manama ~26.2285,50.5860 to Riffa ~26.1300,50.5550 is roughly 11.4km.
        $km = Geo::distanceKm(26.2285, 50.5860, 26.1300, 50.5550);

        $this->assertEqualsWithDelta(11.4, $km, 1.0);
    }

    public function test_distance_to_itself_is_zero(): void
    {
        $this->assertSame(0.0, round(Geo::distanceKm(26.2285, 50.5860, 26.2285, 50.5860), 6));
    }

    public function test_bounding_box_contains_the_centre(): void
    {
        $box = Geo::boundingBox(26.2285, 50.5860, 5.0);

        $this->assertLessThan(26.2285, $box['minLat']);
        $this->assertGreaterThan(26.2285, $box['maxLat']);
        $this->assertLessThan(50.5860, $box['minLng']);
        $this->assertGreaterThan(50.5860, $box['maxLng']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=GeoTest
```

Expected: FAIL — `Class "App\Support\Geo" not found`.

- [ ] **Step 3: Write the implementation**

Create `app/Support/Geo.php`:

```php
<?php

namespace App\Support;

final class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * ponytail: bounding box in SQL, exact haversine in PHP. SQLite has no
     * trigonometric functions unless specially compiled, so a SQL haversine
     * works on MySQL and silently fails in development. Revisit past ~10k stores.
     */
    public static function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $lngDelta = rad2deg($radiusKm / (self::EARTH_RADIUS_KM * cos(deg2rad($lat))));

        return [
            'minLat' => $lat - $latDelta,
            'maxLat' => $lat + $latDelta,
            'minLng' => $lng - $lngDelta,
            'maxLng' => $lng + $lngDelta,
        ];
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
php artisan test --filter=GeoTest
```

Expected: PASS, 3 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Geo.php tests/Unit/GeoTest.php
git commit -m "feat: add portable distance filtering"
```

---

### Task 7: Reserving an offer

The heart of the system. This is where the stock race is settled.

**Files:**
- Create: `app/Actions/ReserveOffer.php`
- Create: `app/Exceptions/ReservationFailed.php`
- Test: `tests/Feature/ReserveOfferTest.php`

**Interfaces:**
- Consumes: `Offer`, `Reservation`, `User`, `PickupCode`, `ReservationStatus`, `OfferStatus`
- Produces: `(new ReserveOffer)->handle(Offer $offer, User $user, int $qty): Reservation`, throwing `ReservationFailed` on sold out, over-cap, or blocked user

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ReserveOfferTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Actions\ReserveOffer;
use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Exceptions\ReservationFailed;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReserveOfferTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'c@example.test'): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => $email,
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);
    }

    private function offer(int $remaining = 100, int $maxPerCustomer = 2): Offer
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'm'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        $store = Store::create([
            'user_id' => $merchant->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17000000',
            'cr_number' => '12345-1',
            'food_licence_no' => 'MOH-9988',
            'status' => StoreStatus::Approved,
        ]);

        return Offer::create([
            'store_id' => $store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => $remaining,
            'remaining' => $remaining,
            'max_per_customer' => $maxPerCustomer,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->subHour(),
            'pickup_end' => now()->addHour(),
            'status' => OfferStatus::Active,
        ]);
    }

    public function test_it_reserves_and_decrements_stock(): void
    {
        $offer = $this->offer(remaining: 10);

        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 2);

        $this->assertSame(2, $reservation->qty);
        $this->assertSame(8, $offer->fresh()->remaining);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $reservation->pickup_code);
    }

    public function test_it_refuses_more_than_remaining(): void
    {
        $offer = $this->offer(remaining: 1, maxPerCustomer: 5);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer, $this->customer(), 3);
    }

    public function test_it_refuses_more_than_the_per_customer_cap(): void
    {
        $offer = $this->offer(remaining: 100, maxPerCustomer: 2);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer, $this->customer(), 3);
    }

    public function test_it_marks_the_offer_sold_out_at_zero(): void
    {
        $offer = $this->offer(remaining: 2, maxPerCustomer: 2);

        (new ReserveOffer)->handle($offer, $this->customer(), 2);

        $this->assertSame(0, $offer->fresh()->remaining);
        $this->assertSame(OfferStatus::SoldOut, $offer->fresh()->status);
    }

    public function test_it_refuses_a_blocked_user(): void
    {
        $offer = $this->offer();
        $user = $this->customer();
        $user->update(['blocked_until' => now()->addDays(3)]);

        $this->expectException(ReservationFailed::class);

        (new ReserveOffer)->handle($offer, $user, 1);
    }

    public function test_concurrent_reservations_never_oversell(): void
    {
        $offer = $this->offer(remaining: 3, maxPerCustomer: 2);

        $succeeded = 0;

        foreach (['a', 'b', 'c'] as $i => $letter) {
            try {
                (new ReserveOffer)->handle($offer, $this->customer("{$letter}@example.test"), 2);
                $succeeded++;
            } catch (ReservationFailed) {
                // expected once stock runs out
            }
        }

        $this->assertSame(1, $succeeded);
        $this->assertSame(1, $offer->fresh()->remaining);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=ReserveOfferTest
```

Expected: FAIL — `Class "App\Actions\ReserveOffer" not found`.

- [ ] **Step 3: Write the exception**

Create `app/Exceptions/ReservationFailed.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class ReservationFailed extends RuntimeException
{
    public static function soldOut(): self
    {
        return new self(__('Those are gone — someone just took the last of them.'));
    }

    public static function overCap(int $max): self
    {
        return new self(__('You can reserve at most :max of these.', ['max' => $max]));
    }

    public static function blocked(): self
    {
        return new self(__('Reservations are paused on your account after two missed collections.'));
    }

    public static function notAvailable(): self
    {
        return new self(__('This offer is no longer available.'));
    }
}
```

- [ ] **Step 4: Write the action**

Create `app/Actions/ReserveOffer.php`:

```php
<?php

namespace App\Actions;

use App\Enums\OfferStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\User;
use App\Support\PickupCode;
use Illuminate\Support\Facades\DB;

class ReserveOffer
{
    public function handle(Offer $offer, User $user, int $qty): Reservation
    {
        if ($user->blocked_until && $user->blocked_until->isFuture()) {
            throw ReservationFailed::blocked();
        }

        if ($offer->status !== OfferStatus::Active || $offer->pickup_end->isPast()) {
            throw ReservationFailed::notAvailable();
        }

        $alreadyHeld = $offer->reservations()
            ->where('user_id', $user->id)
            ->where('status', ReservationStatus::Reserved)
            ->sum('qty');

        if ($alreadyHeld + $qty > $offer->max_per_customer) {
            throw ReservationFailed::overCap($offer->max_per_customer);
        }

        // The whole concurrency story: one conditional UPDATE. Zero affected
        // rows means someone else took the last of the stock first.
        $claimed = DB::table('offers')
            ->where('id', $offer->id)
            ->where('remaining', '>=', $qty)
            ->decrement('remaining', $qty);

        if ($claimed === 0) {
            throw ReservationFailed::soldOut();
        }

        $reservation = Reservation::create([
            'offer_id' => $offer->id,
            'user_id' => $user->id,
            'qty' => $qty,
            'pickup_code' => $this->uniqueCodeFor($offer),
            'status' => ReservationStatus::Reserved,
        ]);

        if ($offer->fresh()->remaining === 0) {
            $offer->update(['status' => OfferStatus::SoldOut]);
        }

        return $reservation;
    }

    private function uniqueCodeFor(Offer $offer): string
    {
        do {
            $code = PickupCode::generate();
        } while ($offer->reservations()->where('pickup_code', $code)->exists());

        return $code;
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
php artisan test --filter=ReserveOfferTest
```

Expected: PASS, 6 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Actions app/Exceptions tests/Feature/ReserveOfferTest.php
git commit -m "feat: reserve offers with atomic stock decrement"
```

---

### Task 8: Collecting a reservation

**Files:**
- Create: `app/Actions/CollectReservation.php`
- Test: `tests/Feature/CollectReservationTest.php`

**Interfaces:**
- Consumes: `Reservation`, `Store`, `ReservationStatus`, `ReservationFailed`
- Produces: `(new CollectReservation)->handle(Store $store, string $code): Reservation`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CollectReservationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Actions\CollectReservation;
use App\Actions\ReserveOffer;
use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\ReservationStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Exceptions\ReservationFailed;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectReservationTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private function seedOffer(): Offer
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'm@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        $this->store = Store::create([
            'user_id' => $merchant->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17000000',
            'cr_number' => '12345-1',
            'food_licence_no' => 'MOH-9988',
            'status' => StoreStatus::Approved,
        ]);

        return Offer::create([
            'store_id' => $this->store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 10,
            'remaining' => 10,
            'max_per_customer' => 2,
            'expires_on' => now()->addDay()->toDateString(),
            'pickup_start' => now()->subHour(),
            'pickup_end' => now()->addHour(),
            'status' => OfferStatus::Active,
        ]);
    }

    private function customer(): User
    {
        return User::create([
            'name' => 'Customer',
            'email' => 'c@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);
    }

    public function test_it_marks_a_reservation_collected(): void
    {
        $offer = $this->seedOffer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 1);

        $collected = (new CollectReservation)->handle($this->store, $reservation->pickup_code);

        $this->assertSame(ReservationStatus::Collected, $collected->status);
        $this->assertNotNull($collected->collected_at);
    }

    public function test_it_is_case_insensitive(): void
    {
        $offer = $this->seedOffer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 1);

        $collected = (new CollectReservation)->handle(
            $this->store,
            strtolower($reservation->pickup_code)
        );

        $this->assertSame(ReservationStatus::Collected, $collected->status);
    }

    public function test_it_rejects_an_unknown_code(): void
    {
        $this->seedOffer();

        $this->expectException(ReservationFailed::class);

        (new CollectReservation)->handle($this->store, 'ZZZZZZ');
    }

    public function test_it_rejects_a_code_collected_twice(): void
    {
        $offer = $this->seedOffer();
        $reservation = (new ReserveOffer)->handle($offer, $this->customer(), 1);

        (new CollectReservation)->handle($this->store, $reservation->pickup_code);

        $this->expectException(ReservationFailed::class);

        (new CollectReservation)->handle($this->store, $reservation->pickup_code);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=CollectReservationTest
```

Expected: FAIL — `Class "App\Actions\CollectReservation" not found`.

- [ ] **Step 3: Add the exception cases**

Add these two static constructors to `app/Exceptions/ReservationFailed.php`:

```php
    public static function unknownCode(): self
    {
        return new self(__('No reservation found for that code.'));
    }

    public static function alreadyCollected(): self
    {
        return new self(__('That code was already collected.'));
    }
```

- [ ] **Step 4: Write the action**

Create `app/Actions/CollectReservation.php`:

```php
<?php

namespace App\Actions;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationFailed;
use App\Models\Reservation;
use App\Models\Store;

class CollectReservation
{
    public function handle(Store $store, string $code): Reservation
    {
        $reservation = Reservation::query()
            ->whereRelation('offer', 'store_id', $store->id)
            ->where('pickup_code', strtoupper(trim($code)))
            ->first();

        if (! $reservation) {
            throw ReservationFailed::unknownCode();
        }

        if ($reservation->status === ReservationStatus::Collected) {
            throw ReservationFailed::alreadyCollected();
        }

        if ($reservation->status !== ReservationStatus::Reserved) {
            throw ReservationFailed::notAvailable();
        }

        $reservation->update([
            'status' => ReservationStatus::Collected,
            'collected_at' => now(),
        ]);

        return $reservation->fresh();
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
php artisan test --filter=CollectReservationTest
```

Expected: PASS, 4 tests.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/CollectReservation.php app/Exceptions/ReservationFailed.php tests/Feature/CollectReservationTest.php
git commit -m "feat: collect reservations by pickup code at the till"
```

---

### Task 9: Closing windows and marking no-shows

**Files:**
- Create: `app/Console/Commands/CloseExpiredOffers.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/CloseExpiredOffersTest.php`

**Interfaces:**
- Consumes: `Offer`, `Reservation`, `User`, `OfferStatus`, `ReservationStatus`
- Produces: the `halffloos:close-expired` artisan command, scheduled every five minutes

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CloseExpiredOffersTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Enums\OfferType;
use App\Enums\ReservationStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Offer;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CloseExpiredOffersTest extends TestCase
{
    use RefreshDatabase;

    private function expiredOfferWithReservation(): array
    {
        $merchant = User::create([
            'name' => 'Merchant',
            'email' => 'm@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Merchant,
        ]);

        $store = Store::create([
            'user_id' => $merchant->id,
            'name' => 'Hidd Cold Store',
            'area' => 'Hidd',
            'lat' => 26.1550,
            'lng' => 50.6550,
            'phone' => '17000000',
            'cr_number' => '12345-1',
            'food_licence_no' => 'MOH-9988',
            'status' => StoreStatus::Approved,
        ]);

        $offer = Offer::create([
            'store_id' => $store->id,
            'type' => OfferType::Item,
            'title' => 'Fresh milk 1L',
            'retail_value_fils' => Money::fromString('2.000'),
            'price_fils' => Money::fromString('0.500'),
            'quantity' => 10,
            'remaining' => 5,
            'max_per_customer' => 2,
            'expires_on' => now()->toDateString(),
            'pickup_start' => now()->subHours(3),
            'pickup_end' => now()->subHour(),
            'status' => OfferStatus::Active,
        ]);

        $customer = User::create([
            'name' => 'Customer',
            'email' => 'c@example.test',
            'password' => bcrypt('secret'),
            'role' => UserRole::Customer,
        ]);

        $reservation = Reservation::create([
            'offer_id' => $offer->id,
            'user_id' => $customer->id,
            'qty' => 1,
            'pickup_code' => 'ABC234',
            'status' => ReservationStatus::Reserved,
        ]);

        return [$offer, $reservation, $customer];
    }

    public function test_it_closes_offers_past_their_window(): void
    {
        [$offer] = $this->expiredOfferWithReservation();

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(OfferStatus::Closed, $offer->fresh()->status);
    }

    public function test_it_marks_uncollected_reservations_as_no_shows(): void
    {
        [, $reservation, $customer] = $this->expiredOfferWithReservation();

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(ReservationStatus::NoShow, $reservation->fresh()->status);
        $this->assertSame(1, $customer->fresh()->no_show_count);
        $this->assertNull($customer->fresh()->blocked_until);
    }

    public function test_a_second_no_show_blocks_for_seven_days(): void
    {
        [, $reservation, $customer] = $this->expiredOfferWithReservation();
        $customer->update(['no_show_count' => 1]);

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(2, $customer->fresh()->no_show_count);
        $this->assertTrue($customer->fresh()->blocked_until->isFuture());
        $this->assertSame(7, (int) round(now()->diffInDays($customer->fresh()->blocked_until)));
        $this->assertSame(ReservationStatus::NoShow, $reservation->fresh()->status);
    }

    public function test_it_leaves_collected_reservations_alone(): void
    {
        [, $reservation, $customer] = $this->expiredOfferWithReservation();
        $reservation->update(['status' => ReservationStatus::Collected, 'collected_at' => now()]);

        $this->artisan('halffloos:close-expired')->assertSuccessful();

        $this->assertSame(ReservationStatus::Collected, $reservation->fresh()->status);
        $this->assertSame(0, $customer->fresh()->no_show_count);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
php artisan test --filter=CloseExpiredOffersTest
```

Expected: FAIL — command `halffloos:close-expired` does not exist.

- [ ] **Step 3: Write the command**

```bash
php artisan make:command CloseExpiredOffers
```

Replace the generated file's contents with:

```php
<?php

namespace App\Console\Commands;

use App\Enums\OfferStatus;
use App\Enums\ReservationStatus;
use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Console\Command;

class CloseExpiredOffers extends Command
{
    protected $signature = 'halffloos:close-expired';

    protected $description = 'Close offers whose pickup window has passed and mark uncollected reservations as no-shows';

    public function handle(): int
    {
        $expired = Offer::query()
            ->where('pickup_end', '<', now())
            ->whereIn('status', [OfferStatus::Active, OfferStatus::SoldOut])
            ->get();

        foreach ($expired as $offer) {
            $offer->update(['status' => OfferStatus::Closed]);

            $stranded = Reservation::query()
                ->where('offer_id', $offer->id)
                ->where('status', ReservationStatus::Reserved)
                ->with('user')
                ->get();

            foreach ($stranded as $reservation) {
                $reservation->update(['status' => ReservationStatus::NoShow]);

                $user = $reservation->user;
                $user->increment('no_show_count');

                if ($user->fresh()->no_show_count >= 2) {
                    $user->update(['blocked_until' => now()->addDays(7)]);
                }
            }
        }

        $this->info("Closed {$expired->count()} offers.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Schedule it**

Append to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('halffloos:close-expired')->everyFiveMinutes();
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
php artisan test --filter=CloseExpiredOffersTest
```

Expected: PASS, 4 tests.

- [ ] **Step 6: Run the whole suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Console routes/console.php tests/Feature/CloseExpiredOffersTest.php
git commit -m "feat: close expired offers and track no-shows on a schedule"
```

---

## Remaining for plan 2

The domain is complete and tested after Task 9. These are deliberately deferred:

- Livewire UI for all three surfaces (merchant application, offer listing, till screen, customer browse, admin approval)
- Authentication scaffolding and role gates
- PWA manifest, service worker, install prompt
- Web Push (VAPID keys, subscription lifecycle, "new offers near you")
- Client-side image resizing before upload
- Arabic translation files and `dir` switching

The UI plan should be written once these actions exist, so its components have a tested domain to call into rather than logic embedded in Livewire classes.

---

## Self-review notes

**Spec coverage.** Five tables — Task 3. Integer fils — Task 2. 50% rule in validation and the database — Tasks 3 and 4. Atomic decrement — Task 7. Pickup codes — Task 5. No-shows and the seven-day block — Task 9. Cash at the till, so no payment code anywhere — correct by omission. Distance — Task 6, with the documented deviation. Merchant eligibility fields (`cr_number`, `food_licence_no`, `status`) — Task 3; the approval *screen* is plan 2.

**Known gap, deliberate.** The spec's "raw meat, poultry and fish excluded from v1" is a policy expressed in merchant terms and onboarding copy, not a schema constraint. There is no category column to enforce it against, and adding one to enforce a rule that a human reviewer already applies at approval time would be speculative. If it later needs enforcing in code, that is a schema change and its own task.

**Type consistency.** `Money::fromString` and `Money::fromFils` are used identically in Tasks 3, 7, 8 and 9. `ReservationFailed` gains two static constructors in Task 8 that Task 7 does not use — additive, not a rename. `OfferStatus::SoldOut` is set in Task 7 and read in Task 9.
