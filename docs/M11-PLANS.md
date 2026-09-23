# M11 product plans

M11 turns the earlier `free`/`paid` capability hook into an organization-scoped product-plan system. The effective entitlement is resolved in this order:

1. organization UUID in `PLAN_COMPLIMENTARY_UNLIMITED_ORGANIZATION_IDS`;
2. an active database grant, including an owner grant or redeemed promotion code;
3. an active Business trial/subscription or a past-due subscription still inside its grace period;
4. the pre-M11 `plan_tier=paid` compatibility value, but only when no subscription record exists;
5. Free.

An expired or cancelled subscription record is authoritative. Changing `organizations.plan_tier` cannot reactivate it.

## Plans

| Capability | Free | Business | Complimentary Unlimited |
|---|---:|---:|---:|
| Price | $0 | US$9/month or US$90/year | $0, no expiry |
| Trial | — | 14 days by default | — |
| Members | 2 | 10 + add-ons | Unlimited |
| Active appointment types | 3 | 25 + add-ons | Unlimited |
| New bookings per organization month | 50 | 500 + 25-booking blocks | Unlimited |
| Active resources | 3 | 25 + add-ons | Unlimited |
| Active person resources | 1 | 10, plus extra-member capacity | Unlimited |
| Active calendar connections | 2 | 10 + add-ons | Unlimited |
| Stored files | 250 MB | 5 GB + 1 GB add-ons | Unlimited |
| Google distance cache misses/month | 25 | 250 + 250-lookup blocks | Unlimited |
| Questionnaire questions, organization-wide | 5 | Unlimited | Unlimited |
| API and outgoing webhooks | No | Yes | Yes |
| Public-page ads | Eligible pages only | No | No |
| Remove Appointment.to branding | No | Yes | Yes |

All numeric defaults can be changed in `.env`. The question count includes active questions plus draft/unused questions; a disabled question retained only because historical answers reference it is not counted. A cancelled booking continues to count in its creation month, and rescheduling does not create another count.

## Business add-ons

| Add-on | Price | Capacity per quantity |
|---|---:|---:|
| Member | $1/month | 1 member |
| Active appointment type | $1/month | 1 type |
| Booking block | $1/month | 25 bookings/month |
| Resource | $1/month | 1 resource |
| Calendar connection | $1/month | 1 connection |
| Storage | $5/month | 1 GB |
| Distance lookup block | $1/month | 250 cache misses/month |

Increases are applied immediately after Stripe accepts the change. When Stripe accepts a reduction, its billing quantity is updated without a current-period credit so the lower quantity is used at the next renewal, while Appointment.to keeps the already-paid capacity available through the current paid period. Current usage must fit the lower capacity when the reduction is requested. M11 never deletes resources, members, appointment types, bookings, calendars, questions, or files to force a downgrade. If usage grows before period end, the lower limit still takes effect on schedule; existing over-limit data remains available, but further additions in that category are blocked.

The pre-M11 `plan_tier=paid` compatibility path includes the base Business limits only. Add-on rows extend capacity only for a Stripe-backed Business subscription; this prevents local or abandoned checkout selections from creating unbilled capacity.

## Platform owner and complimentary access

Every backend user can see their account UUID on the Organizations page. Configure exactly one trusted user:

```dotenv
PLATFORM_OWNER_USER_ID=018f0000-0000-7000-8000-000000000000
```

Only that UUID passes the `manage-platform` gate and can open **Organization → Platform plans**. The owner can search organizations, grant or revoke Complimentary Unlimited, create hashed promotion codes, disable codes, and review audit events. Promotion plaintext is displayed once. Revoking a code prevents later redemptions but does not revoke existing organization grants.

For deployment-controlled accounts, use a comma-separated UUID allowlist:

```dotenv
PLAN_COMPLIMENTARY_UNLIMITED_ORGANIZATION_IDS=018f...0001,018f...0002
```

Environment access cannot be revoked in the web interface. Complimentary status is organization-scoped and does not bypass authentication, authorization, provider limits, security controls, or request throttles. Granting it does not automatically cancel an existing Stripe subscription; the subscriber can still open the portal and schedule cancellation.

## Platform Stripe configuration

Platform subscription credentials are separate from the per-organization Stripe/PayPal credentials used to charge appointment clients. Create these recurring Stripe Prices:

- Business monthly: US$9, monthly;
- Business annual: US$90, yearly;
- each $1 add-on: US$1 monthly and US$12 yearly;
- storage add-on: US$5 monthly and US$60 yearly.

The add-on price interval must match the Business subscription interval. Annual add-on Prices preserve the advertised monthly equivalent but charge 12 months at once. This avoids unsupported mixed-interval Checkout subscriptions. Put every Price ID in the `PLAN_STRIPE_PRICE_*` variables from `.env.example`, then configure the signed webhook endpoint:

```text
POST https://your-host.example/plans/stripe/webhook
```

Subscribe it to `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.paid`, and `invoice.payment_failed`.

## Advertising boundary

When all three AdSense settings are present and enabled, the Google script and ad unit are rendered only for a Free organization's public appointment-type catalog and fully public appointment-type detail page. The script is not loaded on backend, password, unlisted, invitation, questionnaire, contract, booking, payment, upload, management, confirmation, or ticket flows. Business and Complimentary pages never load it.

Deployments remain responsible for AdSense approval, publisher-policy compliance, regional consent/CMP requirements, and matching the public Privacy Policy to their actual configuration.

## Usage and storage details

- Monthly periods use the organization's IANA timezone.
- Distance usage increments only on a Google Routes cache miss.
- Storage includes organization/type logos, all gallery files, every retained contract-template version, questionnaire uploads, and signed-contract uploads.
- Existing over-limit data after a downgrade remains readable. Actions that add to the exceeded category are blocked.
- Gallery photo-count caps remain independently configurable through `GALLERY_*` settings; Complimentary Unlimited bypasses their count caps.
- The hourly `plans:apply-addon-changes` scheduled command applies due reductions.
