# CafeDash v2 — migration & cleanup plan

The new UI is complete and **does not use any old file**. The old pages are still in the folder (by choice, while teammates finish their work) and still run against the same database.

## 1. Database (already applied to `cafedash_db`)

Run in order on any other machine. Both are additive and safe to re-run.

```
C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v2.sql
C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v3.sql
```

| File | Adds |
|---|---|
| `migration_v2.sql` | `User.Created_at`, `User.Deleted_at`, `User_Address`, `Staff`, `Promotion`, `Promotion_Redemption` |
| `migration_v3.sql` | `Payment.Discount_amount`, `Payment.Promo_ID`, `contact_us.Is_read`, `contact_us.User_ID` |

Not dropped yet, because old pages still use them: tables `Admin`, `Driver`; column `User.Address`.

## 2. Old page → new page

| Old (keep or delete) | New |
|---|---|
| `login/loginHandle.php`, `login/start/*` | `index.php` (landing + coffee-cup hero) |
| `login/_member_login.php`, `login/member_login/_login_*` | `auth/login.php` |
| `login/register.php` | `auth/register.php` |
| `login/member_login/_forgot.php`, `_reset_password.php` | `auth/forgot.php`, `auth/reset.php` |
| `login/_admin_login.php`, `login/admin_login/*` | `auth/staff_login.php` |
| `sidebar/home.php`, `home/*`, `api/search/search_cafe.php` | `customer/home.php` + `api/shop/search.php` |
| `sidebar/cafe.php`, `includes/cart_modal.php`, `payment_cart/cart_actions.php` | `customer/cafe.php` + cart drawer (`api/shop/cart.php`) |
| `payment_cart/payment.php`, `create_payment_intent.php`, `finalize_order.php` | `customer/checkout.php` + `customer/checkout_return.php` + `api/shop/checkout.php` + `app/services/payments.php` (Stripe Checkout) |
| `payment_cart/Payment_success.php` | `customer/order_success.php` |
| `sidebar/bills.php` | `customer/orders.php` |
| `sidebar/contact.php`, `contact/contact_us.php`, `includes/html_helpers.php` | `customer/feedback.php` + `api/shop/feedback.php` (old `customer/contact.php` redirects) |
| `sidebar/setting.php`, `api/profile/*`, `profile/avatar_csv_functions.php`, `data/avatar_mapping.csv` | `customer/dashboard.php` (avatars now in `User.Profile_Image`) |
| `admin/admin_panel.php`, `admin/_admin_feedback.php` | `admin/feedback.php` |
| `admin/_admin_members.php` | `admin/members.php` (suspend/deactivate/restore — never hard-deletes) |
| `admin/_admin_restaurants.php` | `admin/restaurants.php` |
| `admin/_admin_ranking.php`, `api/admin/restaurant_transaction_ranking.php` | `admin/overview.php` |
| `admin/_admin_setting.php` | "My account" pop-up in the admin sidebar |
| `admin/_admin_sidebar.php`, `admin/base.php`, `base.php` | `app/layout/master.php` + `app/bootstrap.php` |

## 3. Delete list (62 files) — when the group is ready

Commit first so everything stays recoverable, then from the project folder:

```bash
git rm -r base.php Css js login home sidebar includes contact profile payment_cart \
  admin/base.php admin/admin_panel.php admin/_admin_feedback.php admin/_admin_members.php \
  admin/_admin_ranking.php admin/_admin_restaurants.php admin/_admin_setting.php admin/_admin_sidebar.php \
  api/profile api/search api/admin/restaurant_transaction_ranking.php data/avatar_mapping.csv
```

Afterwards, a migration can drop `Admin`, `Driver` and `User.Address`.

## 4. Before the demo

- **Stripe (set up on this machine):** `composer install` has been run and `config/stripe.php` holds the test keys. Checkout uses Stripe Checkout (Stripe's hosted page). Test card `4242 4242 4242 4242`, any future date, any CVC. On another machine, copy `config/stripe.php` and run `composer install`.
- **Email (password reset):** `config/mail.config.php` with `host, port, username, password, from_email, from_name`. Without it, on localhost the reset link is shown on screen instead.
- **Staff locked out / email down:** run `C:\xampp\php\php.exe tools\reset_staff_password.php --list`, then `C:\xampp\php\php.exe tools\reset_staff_password.php <email>` to print a new one-time password. Super Admins can also use Staff & roles → ⋯ → Reset password.
- `config/` and `vendor/` are in `.gitignore`; never commit the keys.

## 5. New structure

```
app/bootstrap.php          session, DB, CSRF, auth guards, JSON helpers
app/layout/                master.php (the new base.php), icons.php, auth_visual.php
app/services/              cart, promo, payments (Stripe), mailer, images (safe uploads)
app/loyalty.php            1 point per RM1, tiers
auth/ customer/ admin/     pages
api/shop/ api/customer/ api/admin/   JSON endpoints (POST + CSRF, return {ok, data, error})
assets/css/core/           tokens · base · layout
assets/css/components/     surfaces · buttons · forms · feedback · overlays · data · cart · cafe-card
assets/css/pages/          landing · auth · dashboard · shop · admin
assets/js/core/            api · ui · motion · cart
assets/js/pages/           one script per page
assets/vendor/             GSAP 3.12.5 + ScrollTrigger (offline)
```
