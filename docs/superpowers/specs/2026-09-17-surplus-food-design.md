# Halffloos — Bahrain Surplus Food Marketplace

**Date:** 2026-09-17
**Status:** Design approved, not yet implemented

Name: *Halffloos* — English "half" plus Arabic **فلوس** (*floos*, money).
Repository: https://github.com/er-athulbaby/halffloos

## Problem

Bahrain is the top Arab country for food waste and fourth in the world — roughly
132kg per person per year, around 250,000 tonnes annually, costing about
BD 94.9 million. During Ramadan it exceeds 400 tonnes a day. Food waste has risen
23% since 2022.

At the same time, food prices — particularly meat and vegetables — have risen
sharply in a country that imports nearly all of its food.

Registered merchants throw away stock that is still within its shelf life because
it expires tomorrow. Nobody is matching that stock to people who would happily buy
it at a steep discount.

## What we are building

A progressive web app where registered Bahraini merchants list near-expiry stock
at a minimum of 50% off, and customers reserve it and collect it in person.

Positioning is **cost of living first, waste reduction second**. "Meat and
vegetables got expensive" motivates purchase; "fight food waste" is the side
effect.

Worked example: a shop has 100 litres of milk expiring tomorrow, normally
BHD 2.000. Listed at BHD 0.500. The shop recovers BHD 50 instead of binning
BHD 200 of stock, and 50 people walk into the store to collect.

## Non-goals

- Operating delivery. Pickup only. Merchants who already employ a driver may
  self-arrange; we build no logistics.
- Payment processing. Customers pay the merchant at the merchant's own till.
- Own driver fleet, ever.
- Ratings, reviews, chat, disputes, waitlists.
- Multi-city or multi-country.

## Users

| Role | Who | Primary need |
|---|---|---|
| Customer | Budget-conscious residents, heavily expat | Cheap food near them, today |
| Merchant | Staff at a registered shop | List stock in under 30 seconds at closing |
| Admin | The operator | Vet merchant applications, police abuse |

## Merchant eligibility

Registered companies only. Every store must supply:

- A valid Commercial Registration (CR) number, verifiable on Sijilat
- A valid Ministry of Health food licence number
- Photographs of both documents

Applications are reviewed and approved manually. This gate is the entire liability
story and must not become self-serve.

**Go-to-market consequence.** Chains such as Lulu, Carrefour and Ramez cannot be
signed cold — they route through category managers and will ask for user numbers
that do not yet exist. Start with CR-holding independents: neighbourhood cold
stores, independent supermarkets, bakeries, butchers. They pass the same
eligibility rule, and the owner decides in one conversation. Approach chains once
there are real listings and real users. This is the order Too Good To Go used.

## Data model

Five tables.

### users

`name`, `phone`, `email`, `role` (`customer`|`merchant`|`admin`), `no_show_count`,
`blocked_until`

### stores

`name`, `name_ar` (optional), `area`, `lat`, `lng`, `phone`, `pickup_instructions`,
`image`, `cr_number`, `food_licence_no`, `status` (`pending`|`approved`|`suspended`),
`verified_at`, `verified_by`, `delivers` (bool), `delivery_fee_fils` (nullable),
`user_id`

### offers

`store_id`, `type` (`item`|`lot`), `title`, `description`, `barcode` (nullable),
`retail_value_fils`, `price_fils`, `quantity`, `remaining`, `max_per_customer`,
`expires_on` (the product's expiry date), `pickup_start`, `pickup_end`, `image`,
`status` (`active`|`sold_out`|`closed`)

`sold_out` is set when `remaining` hits zero. `closed` is set by the scheduled job
when `pickup_end` passes.

`quantity` is the total listed and never changes. `remaining` is current stock and
is decremented by reservations.

### reservations

`offer_id`, `user_id`, `qty`, `pickup_code`, `status`
(`reserved`|`collected`|`no_show`|`cancelled`), `collected_at`

### push_subscriptions

`user_id`, `endpoint`, `keys`

## Authentication

Email and password, using Laravel's built-in scaffolding. Customers self-register;
merchant accounts are created by the admin on approval and never self-register.

Phone is captured but not used as the login identity in v1. Phone OTP is the better
fit for this audience but requires an SMS provider and per-message cost — add it
when email friction actually shows up in signup drop-off.

## Listing types

Two types, one enum column, same table.

- **`item`** — packaged goods with a printed expiry date: dairy, bread, juice,
  tinned, dry goods. Listed individually. The merchant scans the barcode, which
  prefills the name, then enters expiry, price and quantity.
- **`lot`** — fresh produce and prepared food, which have no meaningful date.
  Listed as a bundle: "mixed vegetable box, BHD 1.500".

**Raw meat, poultry and fish are excluded from v1.** Near-expiry raw protein with
a pickup window and no controlled cold chain between listing and collection is the
highest-liability category in the system. Revisit only after the Ministry of Health
and municipality rules have been checked by a professional.

## The 50% rule

A listing is rejected unless `price_fils * 2 <= retail_value_fils`. Enforced in one
validation rule and one database check constraint, not scattered through the code.

50% is a floor, not a target. The milk example above is 75% off.

Retail value is merchant-declared, so it is an honour system. Police it with a
report button and admin spot-checks, not upfront verification.

## Reservation flow

1. Customer browses offers near them — today's pickup windows only, sorted by
   distance.
2. Customer picks a quantity, capped by `max_per_customer` and `remaining`.
3. Reserve: stock decrements atomically, a six-character pickup code is issued.
4. Customer shows the code at the shop inside the pickup window.
5. Merchant types the code, marks it collected, and takes payment at their own till.
6. A scheduled job closes expired windows and marks uncollected reservations
   `no_show`.

### Stock concurrency

With 100 units and many simultaneous buyers, the last units race. Solved in one
statement — no locks, no queue:

```sql
UPDATE offers SET remaining = remaining - :qty
WHERE id = :id AND remaining >= :qty
```

Zero affected rows means sold out. That is the entire concurrency story.

### Cancellation

Allowed any time before collection. Restores stock.

### Pickup codes

Six alphanumeric characters, unique within an offer. A merchant never sees another
store's codes, so global uniqueness is unnecessary.

### No-shows

Free reservations get abandoned — this is precisely why Peekabox and Too Good To Go
charge upfront. Without a gateway, the controls are:

- No-show count per user
- Automatic block on reservations for 7 days after two no-shows
- Short pickup windows

Measure the rate from the `no_show` status. If it rises to the point merchants
complain, that is the trigger to add payments.

## Payments

**None in v1.** The customer pays the merchant directly at the merchant's till
through their existing POS.

The reason is regulatory, not effort. Collecting customer money and remitting it to
merchants later means holding funds on behalf of others, which moves toward payment
services activity regulated by the Central Bank of Bahrain. Staying out of the money
flow removes payouts, escrow, reconciliation, refunds, PCI scope and that entire
question.

**Trigger to revisit:** the measured no-show rate materially annoys merchants, or a
chain requires prepayment. Implementation would then be Tap Payments or PayTabs,
both of which support BenefitPay. Not Stripe.

**If the product is ever monetised,** use a flat merchant subscription rather than
commission. Commission forces you into the money flow and everything above;
subscription does not.

## Delivery

Not built. A bag sells for BHD 1.500–3.000 and delivery in Bahrain costs
BHD 1.000–2.500 a drop, so the fee approaches the price of the goods. Surplus also
appears in a narrow closing window at a handful of units per store across a
scattered island — the worst possible density for delivery.

Hedge, requiring no logistics: `stores.delivers` and `stores.delivery_fee_fils`.
Merchants who already employ a driver tick the box and arrange it themselves.

## Onboarding and admin

**Merchant application:** store name, CR number, food licence number, contact
person, phone, map pin, document photos. Saved as `pending`.

**Review:** verify the CR on Sijilat, check the licence, approve or reject. On
approval a merchant user is created and a login link sent.

**Merchant daily screen** — the single most important interface in the product. A
shop worker at 9pm sees one screen:

- Scan barcode or type name, then expiry date, original price, price, quantity,
  pickup window
- Inline rejection of anything under 50% off
- Today's listings with live remaining counts
- A "repeat yesterday" button that clones a previous row
- One large field for typing a pickup code to mark collection

The merchant session stays logged in on the shop's device. No re-authentication at
the till.

**Admin — four screens:** pending applications, stores (with suspend), offers (spot
check for inflated retail values), reports.

## Internationalisation

Ship English only. Wire the plumbing from the first line of code.

- All UI strings go through `__()`. Costs nothing while writing, costs a week to
  retrofit.
- Tailwind logical properties (`ps-*`/`pe-*`, never `pl-*`/`pr-*`) so RTL works by
  flipping `dir`.
- **UI chrome is translated; merchant-entered content is not.** A shop worker types
  the product name once, in whatever language they use. `stores.name_ar` stays
  optional for chains that want it.

Arabic is required before approaching any chain or regulator. Hindi, Malayalam,
Bengali and Tagalog are deferred until users ask.

## Stack

- Laravel, Livewire, Tailwind
- MySQL 8 / MariaDB
- Installable PWA, with a `share_target` entry if link-sharing is ever wanted
- Web Push for "new offers near you"
- Database queue driver and the Laravel scheduler for window closing and no-show
  marking

No Filament. Four admin screens is less code than installing and configuring it,
and it introduces a desktop-shaped asset pipeline into a PWA build.

Native apps are deferred. Android covers most of the Bahraini market and a Flutter
shell can be added later without touching the backend.

## Engineering decisions that are expensive to reverse

1. **Money is stored as integer fils.** BHD has three decimal places. `BIGINT`
   columns and a `Money` value object. No floats anywhere. Retrofitting this is a
   full-schema migration.
2. **Atomic stock decrement** as specified above, from the first reservation.
3. **Distance by haversine in the query.** Bahrain has at most a few hundred stores,
   so no PostGIS and no spatial index.
   `ponytail: naive distance scan, revisit past ~10k stores`.
4. **Translation and RTL plumbing from day one**, per the internationalisation
   section.

## Risks

| Risk | Response |
|---|---|
| Food safety liability | Registered merchants only, expiry shown on every listing, merchant is seller of record, raw protein excluded from v1. **MoH and municipality rules must be checked by a professional before launch.** |
| Cannibalising full-price sales — the objection every merchant will raise | List only on the final day before expiry, cap per customer, pickup window at end of day |
| Cold start: no users means no chains, no chains means no users | Start with CR-holding independents, not chains |
| UAE incumbents crossing the causeway — Peekabox (1,000+ outlets, $1.5m seed), Platable and BonApp all target the wider GCC | Time window of roughly 12–24 months. Local presence and walk-in merchant sales are the only real advantage |
| Demand may not exist: OLIO is listed in Bahrain and has no listings | OLIO is peer-to-peer free sharing with no economic engine — a different mechanism. The open risk is cultural resistance to buying reduced food, which only launch will settle |
| No-show rate under free reservations | Tracked and auto-blocked; triggers the payments decision |

## Launch prerequisites

- Ministry of Health and municipality food safety rules reviewed by a professional
- Terms of service making the merchant the seller of record
- Personal Data Protection Law (Law 30 of 2018) basics: consent, export, erasure

## Out of scope for v1

Payments, delivery, raw protein, ratings, reviews, chat, waitlists, partial
collection, multi-staff merchant logins, merchant analytics, bulk upload, self-serve
merchant approval, native apps, languages beyond English.

Each is added when a real user or merchant asks for it.
