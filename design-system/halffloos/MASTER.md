# Halffloos — Design System (MASTER)

**Global source of truth.** Page-specific overrides live in `pages/<page>.md` and
win over this file. If no page file exists, these rules apply.

**Date:** 2026-09-17
**Stack:** Laravel + Livewire + Tailwind, delivered as an installable PWA

> Sourced from the ui-ux-pro-max catalog by reading `data/*.csv` directly. The
> skill's ranking script requires Python, which is not installed on this machine,
> so the product/style/palette matching below was done by hand against the same
> data. Values are catalog values; the selection is a judgement call.

---

## 1. Product pattern

Halffloos is a blend of two catalog product types, not one:

| Catalog entry | What we take from it |
|---|---|
| **#106 Grocery & Shopping List** | Fresh-food palette, category grouping, barcode scan to list, quantity stepper |
| **#171 Classifieds / Buy-Sell** | Photo-first listing cards, location radius filter, verified-seller badge, price-led hierarchy |

Deliberately **not** #95 Food Delivery / On-Demand. That pattern is built around
appetising warm oranges, live map tracking and driver ETA — we have no delivery
and no drivers, and its red/orange palette reads as urgency and warning, which is
exactly wrong for food someone already suspects is past its best.

The verified-seller badge from #171 maps directly onto our CR and MoH licence
verification. Use it prominently — it is the single strongest trust signal we have.

## 2. Style

**Primary:** Flat Design + Vibrant & Block-based
**Secondary:** Micro-interactions

Both source entries agree on this pairing. Flat keeps the PWA light and fast on
mid-range Android, which is most of the market. Block-based cards give each offer
a clear price and expiry without ornament.

No glassmorphism, no neumorphism, no skeuomorphic textures. They cost performance
and add nothing to a list of discounted milk.

## 3. Color

Catalog palette **#106 Grocery & Shopping List** — "Fresh green + food amber".

Green is doing real work here. The biggest risk in the product spec is people
assuming near-expiry means spoiled. Green reads as *fresh*; red and orange read as
*warning*. This is a trust decision, not a taste one.

```css
:root {
  --primary:            #059669;  /* emerald 600 */
  --on-primary:         #000000;
  --secondary:          #10B981;
  --on-secondary:       #000000;
  --accent:             #D97706;  /* amber — expiry countdown, urgency */
  --on-accent:          #000000;
  --background:         #ECFDF5;
  --foreground:         #0F172A;
  --card:               #FFFFFF;
  --card-foreground:    #0F172A;
  --muted:              #F0F8F6;
  --muted-foreground:   #475569;
  --border:             #E1F2ED;
  --destructive:        #DC2626;
  --on-destructive:     #FFFFFF;
  --ring:               #059669;
}
```

**Do not "fix" `--on-primary` to white.** Black on `#059669` clears 4.5:1; white on
it does not. This looks wrong to designers and is correct. Same for `--on-accent`.

Semantic use:

- **Green** — price, savings, confirmed state, the reserve button
- **Amber** — expiry proximity, pickup window closing, low stock
- **Red** — errors and destructive actions only. Never for expiry. Red on a food
  listing means "this is bad," which is the opposite of the message.

## 4. Typography

**Tajawal**, single family, headings and body.

The catalog's e-commerce pairing is Rubik + Nunito Sans, and it's a good pairing —
but neither covers Arabic. The spec commits to Arabic before approaching any chain
or regulator, so picking a Latin-only family now buys a font swap later.

Tajawal carries `arabic | latin` in the catalog, is widely used in Gulf digital
products, and one family means one font load — which matters on a PWA served to
mid-range phones.

Fallback if Tajawal is rejected: **Almarai**, same dual-script coverage.

```
Base size      16px — never below 12px for body
Line height    1.5 body, 1.2 headings
Weights        400 body, 500 UI labels, 700 prices and headings
Numerals       tabular for prices and countdowns, so digits don't jitter
```

Prices are the most-scanned element on every screen. Set them at 700 weight and at
least one step above the product name.

## 5. Spacing and density

Two densities, because the two audiences use this in opposite conditions.

| Surface | Density | Scale |
|---|---|---|
| Customer browse, offer detail | Standard | 16 / 24 / 32 / 48 / 64px |
| Merchant listing + till screen | Dense | 8 / 12 / 16 / 24 / 32px |

The merchant screen is used one-handed, at speed, at closing time. Tighter spacing,
bigger targets — those are not in conflict, the space comes out of margins, not
out of the tap areas.

## 6. Layout and RTL

- Mobile-first. Design at 360px, then scale up.
- **Tailwind logical properties only** — `ps-*`/`pe-*`/`ms-*`/`me-*`, never
  `pl-*`/`pr-*`/`ml-*`/`mr-*`. RTL then works by flipping `dir`, with no second
  stylesheet.
- Bottom navigation, maximum 5 items. Current plan: Browse, Reservations, Account.
- Respect safe areas — `env(safe-area-inset-bottom)` on the bottom nav.
- No horizontal scroll at any width. Never disable pinch zoom.

## 7. Key screens

### Offer card (the most important component)

Photo-first, price-led. In order of visual weight:

1. Product photo (reserve the box — no layout shift on load)
2. **Price** in green, with the original struck through beside it
3. Discount badge — "70% off" — this is the hook
4. Expiry date, in amber when it's tomorrow
5. Store name + verified badge
6. Distance and pickup window

Every card states the expiry date explicitly. Never hide it behind a tap. Hiding it
is what makes people distrust the whole app.

### Merchant listing screen

One screen, thumb-reachable, no scrolling to submit. Barcode scan first, then
expiry, original price, price, quantity, window. The 50% rule rejects inline, next
to the price field — never as a summary error at the top.

"Repeat yesterday" is the primary action for a returning merchant, not a secondary
one.

### Till / collection screen

One large input for the six-character pickup code. Large type, numeric-friendly,
autofocus. A shop worker is holding a phone in one hand and stock in the other.

## 8. Motion

Subtle tier. Micro-interactions only: button press feedback, card tap, stock
counter ticking down, reserve confirmation.

- Animate `transform` and `opacity` only. Never `width`/`height`/`top`/`left`.
- Honour `prefers-reduced-motion`.
- Exit faster than enter.
- No scroll choreography, no pinning, no parallax. This is a utility people open for
  ninety seconds.

## 9. Non-negotiables

- Contrast 4.5:1 on all text
- Touch targets 44×44px minimum, 8px apart
- Visible focus rings — never removed
- SVG icons only, **never emoji as icons**
- Visible labels on form fields, not placeholder-only
- Errors beside the field, not summarised at the top
- Reserve space for images so CLS stays under 0.1
- WebP/AVIF, lazy-loaded below the fold

## 10. Anti-patterns for this product

| Avoid | Why |
|---|---|
| Red or orange as the dominant color | Signals spoiled and warning on a food product people are already unsure about |
| The word "expired", "waste", "leftover", or "old" anywhere in the UI | Cost-of-living framing, not waste framing. Say "expires tomorrow" and "save 70%" |
| Hiding the expiry date behind a tap | Destroys trust across the whole app, not just that listing |
| Emoji as category icons | Renders inconsistently across Android, looks unprofessional to merchants |
| Placeholder-only labels on the merchant form | Used at speed, in poor light, by someone who is not the app's owner |
| Glassmorphism, heavy shadows, gradients | Costs frames on mid-range Android for zero communicative value |
| Latin-only fonts | Guarantees a font swap when Arabic ships |

---

## Verification status

Palette, product pattern and style come from catalog rows #106, #171 and #40.
Typography is a substitution away from the catalog's recommendation, justified in
section 4. Contrast ratios for `--on-primary` and `--on-accent` should be
re-checked with a contrast tool before launch, not taken on trust from this file.
