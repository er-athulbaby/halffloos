# Halffloos v1 — decisions taken during execution

Nineteen rulings made while executing `2026-09-17-halffloos-v1.md`, in the order
they were made. Each says what was decided, why, and what it costs if it was the
wrong call. They are recorded here rather than left in the (git-ignored) execution
ledger, because a decision taken on someone's behalf that dies with a scratch
directory was made in secret.

Reworking any of these is expected and cheap. Nothing here is load-bearing beyond
what its own "cost if wrong" line states.

## Pre-flight (from scanning the plan before execution)

**1. Keep Task 6 (`Geo`) despite it having no consumer in this plan.** The
bounding-box approach is a deliberate deviation from the spec's "haversine in the
query", which fails on SQLite. An uncommitted, untested deviation is exactly what a
later implementer silently reverts. *Cost if wrong: ~40 unused lines until plan 2.*

**2. Add an UPDATE trigger alongside the INSERT trigger.** The spec wants the 50%
rule enforced by the database; an INSERT-only constraint lets a merchant edit a
price past the floor once plan 2 ships the edit UI. *Cost if wrong: none.*

**3. Rename `test_concurrent_reservations_never_oversell`.** It runs sequentially;
PHP cannot exercise real concurrency in-process. A test whose name overclaims its
coverage is a defect. *Cost if wrong: none, naming only.*

## Task 1 — scaffold

**4. Restore Tailwind's `@source` directives.** The plan said "replace the contents"
of `app.css`, which silently dropped content scanning — Livewire and pagination
markup would have rendered unstyled, surfacing only in plan 2. Plan text corrected.
*Cost if wrong: none.*

**5. Scope the task review to files where the task made a decision.** The full range
was 59 files / 13,239 insertions, overwhelmingly the Laravel skeleton and lock
files. *Cost if wrong: a defect inside an untouched skeleton file goes unreviewed
until the whole-branch review, which does cover the full range.*

**6. Defer Tajawal's Arabic subset to plan 2.** `subsets: ["latin"]` means Arabic
glyphs are not fetched. The family choice was the load-bearing part and is
committed; the subset is one word in `vite.config.js` at Arabic launch, and the
glyphs are real payload on mid-range phones today. *Cost if wrong: Arabic renders in
a fallback font until that word changes.*

## Task 2 — Money

**7. Fix the float in `percentOffFrom` rather than exempt the plan.** The plan (mine)
specified float division, violating the binding "no floats in money" rule. `intdiv`
gives identical floor semantics exactly. *Cost if wrong: none.*

**8. Promote the `MoneyCast` raw-int guard out of Minor.** Nominally minor since no
caller passes a raw int, but the failure was a silent 1000× money error. It now
throws. Batched into the same dispatch, so the fix loop was not extended.
*Cost if wrong: a future caller gets an exception instead of a wrong number.*

## Task 3 — schema

**9. Do not switch models from `$guarded = []` to explicit `$fillable`.** Tasks 7–9
build attribute arrays in code from validated parameters, never request input, so
the exposure is not live in this plan. The real exposure is plan 2's forms, where
the correct fix is validation at the form boundary. Switching also introduces a
silent failure mode — a column omitted from `$fillable` simply does not persist —
**which then bit twice during Tasks 7 and 9 on the one model that does use it.**
*Cost if wrong: a plan-2 component passing request data to `create()` could let a
merchant set `store_id` or self-approve via `status`.* **Carried into plan 2's
handover notes.**

## Tasks 4–6 and 8–9 — batching

**10. Batch Tasks 4–6 into one dispatch and one review.** Same shape, no
interdependencies. *Cost if wrong: less individual attention; mitigated by requiring
per-task spec verdicts.*

**11. Batch Tasks 8–9 likewise.** *Cost if wrong: Task 9's block logic shares a
review with Task 8; mitigated by escalating the reviewer's model.*

## Task 7 — reservations

**12. The per-customer cap counts `reserved` + `collected` + `no_show`; only
`cancelled` frees it.** Counting only `reserved` let a customer collect their cap and
immediately re-reserve, defeating the cap's purpose. A cancellation returns the
stock so it returns the allowance; a no-show returns neither. *Cost if wrong: a
customer wanting more after collecting waits for the next listing.*

**13. A bad `qty` throws `InvalidArgumentException`, not `ReservationFailed`.** Zero
or negative quantity is a programming error at the domain boundary, not something a
customer can reach. Conflating them would let a real bug surface as a polite
message. *Cost if wrong: an unvalidated caller gets an unhandled exception; Task 8
validates at the request layer.*

**14. Carry the stale-`$offer` problem forward as a dispatch constraint rather than
fixing it.** `ReserveOffer` returns a `Reservation`, not an `Offer`; refreshing the
caller's model would be the action reaching outside its own result. *Cost if wrong:
a caller re-saves a stale offer and clobbers `remaining`.*

## Tasks 8–9 — collection and the sweep

**15. Leave the no-show counter non-decaying.** Once a user reaches two, every
subsequent no-show re-blocks for 7 days, forever. A rolling block from the most
recent offence is defensible and the spec sets no decay policy — but this is an
unowned product decision, not a technical one. *Cost if wrong: someone who no-shows
twice a year apart is treated like a repeat offender.* **Worth revisiting once real
no-show rates exist.**

**16. Leave the scheduler's console output untranslated.** The `__()` rule targets
what customers and merchants see, not operator diagnostics. *Cost if wrong: one
console line stays English.*

**17. Promote the untested cross-store guard out of Minor.** It is the line stopping
merchant B collecting merchant A's reservation, and an untested security guard is
one refactor from vanishing. *Cost if wrong: one extra test to maintain.*

## Parked after the final fix wave

**18. `quantity` edited downward below the live-reservation total makes cancellation
throw permanently rather than degrade.** Fail-safe rather than corrupting, and
nothing in this plan edits `quantity`. *Cost if wrong: a customer cannot cancel
until the data is corrected by hand.*

**19. `CollectReservation` still has no pickup-window check.** A scheduler outage
leaves codes redeemable indefinitely. Adding a check would make an outage *refuse*
legitimate customers instead, which is worse for merchant trust — the right fix is
scheduler monitoring. *Cost if wrong: during an outage, someone collects after the
window closed.* **Carried to plan 2.**

---

## What the reviews caught that the plan got wrong

Worth recording, because it is the argument for keeping the review step:

- **Cancellation was in the spec, never implemented, and never deferred.** The plan
  simply omitted it. Found only by the whole-branch review. Its failure scenario is
  the product's own worst case: an offer sells out, a customer cancels, and the
  freed units can never be reserved again — the food goes in the bin.
- **`qty = -1` passed the cap check and *increased* stock.**
- **The exactly-half boundary was untested at the database.** Changing either trigger
  to `>=` passed all 42 tests while rejecting every genuine 50%-off listing — the
  spec's own named floor.
- **A stale reservation sharing a pickup code could refuse a valid customer at the
  till**, and pickup codes were unique per offer but looked up per store.
- **The sweep could strike a customer standing at the counter as a no-show.**
