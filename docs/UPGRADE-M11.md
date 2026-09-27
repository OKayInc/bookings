# Upgrade to M11

M11 is an additive upgrade from M10-R2. Back up the database and stored files first. Do not run `migrate:fresh`.

```bash
php artisan optimize:clear
php artisan migrate
php artisan test
```

Then:

1. Copy the M11 variables from `.env.example` and review every Free/Business cap.
2. Sign in once, copy your account UUID from **Organizations**, and set `PLATFORM_OWNER_USER_ID` to that exact UUID.
3. Create the Stripe recurring Prices described in `docs/M11-PLANS.md`, including monthly and annual Prices for every add-on, configure the `PLAN_STRIPE_*` values, and register the platform billing webhook.
4. Keep Laravel Scheduler running every minute. M11 adds the hourly `plans:apply-addon-changes` task.
5. Configure AdSense only after approval and any required consent tooling is ready. Leave `PLAN_ADSENSE_ENABLED=false` otherwise.
6. Verify Free limits in a test organization, complete a Stripe test-mode checkout, send signed webhook events, and confirm API/webhook access changes with the effective plan.

Organizations manually set to `plan_tier=paid` before M11 retain Business features until an M11 subscription record is created. Once one exists, its status becomes authoritative. No existing organization data is deleted during migration or downgrade.

Rollback drops M11 plan subscriptions, add-ons, grants, promotion/redemption records, usage counters, audit/webhook records, and the branding preference. It does not delete organization content or cancel subscriptions at Stripe; cancel provider-side subscriptions before rolling back in production.
