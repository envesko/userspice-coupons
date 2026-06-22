# Coupons - AI instructions

Create and redeem coupons with limited/unlimited uses that grant UserSpice permission levels (or
other custom rewards). Maintained by Envesko.

## Public functions (functions.php)
- `Coupons_getCoupons($code)` - look up a coupon by code; returns state/data/error.
- `Coupons_redeemCoupon(...)` / generation + permission-reward helpers (see functions.php).

## Tables
`coupons` · `coupons_history` (redemptions) · `coupons_permissions` (rewards granted) ·
`coupons_required_permissions` (gates who may redeem) · `coupons_rewards`.

## Lifecycle files
install.php (tables) · activate.php (status active) · uninstall.php (deactivate, retain data) ·
delete.php (drop tables) · migrate.php (numbered updates) · configure.php (admin UI) ·
files/ (webroot-copied UI + `api.php` redeem/lookup endpoint).

## Key conventions
The `files/api.php` endpoint is master/permission-2 gated + CSRF-checked (`Token::check`). SQL is
parameter-bound. Bumped to UserSpice 6.1.0. `update.php` is deprecated (now a stub); schema changes
go in `migrate.php`.
