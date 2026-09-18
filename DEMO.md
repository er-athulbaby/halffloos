# Running the demo

## Start it

```bash
php artisan migrate:fresh && php artisan db:seed --class=DemoUsersSeeder
npm run build
php artisan serve
```

Then open <http://127.0.0.1:8000>. **Use a phone-sized window** — every screen is
designed mobile-first and the shop staff will be on a phone.

If `php` is not found, it lives at `~/.config/herd-lite/bin/php.exe`.

## Accounts

All three passwords are `password`.

| Role | Email | Lands on |
|---|---|---|
| Shop | `merchant@halffloos.test` | List stock |
| Customer | `fatima@halffloos.test` | Browse |
| Admin | `admin@halffloos.test` | Shops |

The seed creates two approved shops (Hidd Cold Store, Adliya Bakery) with eight
items between them, and one shop waiting for approval (Seef Fresh Market).

## The five-minute walkthrough

**1. The shop lists what is left.** Sign in as the shop. Type a name, pick a
category, enter the normal price and yours. Try `2.000` and `1.500` first — the
screen refuses it at 25% off before you can submit. Change to `0.500` and it lists.

Mention: listing takes about twenty seconds, at closing time, one-handed.

**2. The customer finds it.** Sign in as the customer. Filter by category, or search.
Prices are shown against the normal price, so the saving is the headline.

**3. The customer reserves.** Tap Reserve. A six-character code appears, and the
stock count drops immediately. Go to My codes — the code is saved there, so it
survives closing the app.

Mention: no money moves through Halffloos. The customer pays the shop.

**4. The shop hands it over.** Sign back in as the shop, tap Collect, type the code
in any case. The screen says exactly what to hand over and what to charge.

**5. Admin vets a shop.** Sign in as the admin. Seef Fresh Market is waiting, with
its CR number and Ministry of Health food licence shown for checking. Nothing can be
listed until someone approves it.

## Things worth saying out loud

- **The 50% floor is enforced in three places** — as you type, on submit, and by the
  database itself. A shop cannot list a token discount.
- **Raw meat, poultry and fish cannot be listed at all.** Near-expiry raw protein
  with no controlled cold chain is the highest-liability category, so there is no
  category to put it in.
- **Prices are exact.** BHD has three decimal places and everything is stored in
  fils as whole numbers, so nothing rounds wrongly.
- **Cancelling returns the stock** to the shop, and reopens a sold-out listing.
- **A customer at the counter cannot be marked a no-show** — the sweep waits thirty
  minutes past the window before it counts anyone as missed.

## What is not built yet

Say so plainly rather than talking around it:

- No push notifications, so nobody is told when something is listed near them
- No Arabic yet, though the plumbing is in place
- Not installable as an app from the home screen yet
- Barcode scanning needs HTTPS in production; it works on localhost
