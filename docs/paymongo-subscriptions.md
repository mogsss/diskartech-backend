# Employer Premium test checkout

The employer profile shows Basic/Premium from the existing `isSubscribed` and `subscription_expires_at` fields. The subscription screen now opens PayMongo Hosted Checkout instead of displaying a simulated success alert. Prices are controlled by Laravel: PHP 149 (14900 centavos) for a calendar month, or PHP 1490 (149000 centavos) for a calendar year. Monthly/yearly dates clamp safely at month ends. Renewal extends an existing paid period; this is a one-time purchase with no automatic recurring charge.

This implementation accepts **test keys and test payments only**. Test purchases update the demo account's Premium state. They do not represent paid live subscriptions. Before going live, implement and review live-mode support, clean up demo entitlements, and confirm payment methods/account approval. The first Premium benefit is the active job allowance below. Featured listings, repost shortcuts, hiring reports, and priority support are not included.

## Active job allowance

Employer Basic accounts can have **3 active job posts**; an active Premium subscription allows **unlimited active job posts**. Laravel checks the subscription flag and UTC expiry on every create/reopen request. Expired subscriptions immediately use the Basic limit. Existing over-limit posts are retained and can be edited or closed, but new posts/reopens require the count to fall below the Basic limit. Closing/deleting frees a slot; closed/inactive/archived statuses do not count. Moderation-hidden open posts and legacy blank statuses reserve a slot, matching the existing employer jobs screen.

The check applies to `/api/jobs` creation, employer status changes, and admin reopen actions. Existing active/hidden posts can keep their state at or above the limit. Transactions lock the account/profile before counting and writing, serializing competing create/reopen requests on MySQL. Household posting is unchanged. Verification requirements continue to apply to new posts. Client-supplied plan/limit fields cannot increase the allowance.

`GET /api/employer/job-quota` and `/api/employer/jobs` return current usage; subscription responses include `subscription.job_quota`. Premium represents unlimited with `active_job_limit: null` and `remaining_job_slots: null`; `can_post_job` is true while Premium is active. Mobile Jobs, new-job forms, and subscription cards show Unlimited instead of a numeric denominator. A server rejection uses HTTP 422 with `errors.active_job_limit`, including when the plan expires or another device fills the last Basic slot while a form is open. A blocked draft is retained so the employer can close a listing or upgrade, then retry. No additional migration or environment variable is needed for job quotas.

## Configuration and deployment

Place credentials only in backend `.env` and Render Environment:

```dotenv
PAYMONGO_SECRET_KEY=sk_test_your_test_secret
PAYMONGO_PAYMENT_METHODS=qrph
PAYMONGO_WEBHOOK_SECRET=your_endpoint_signing_secret
```

The existing secret key is sufficient to create checkouts and manually verify payments. The webhook secret is the separate signing secret for your registered endpoint, not the API secret key. Never put either secret in the mobile app or source control. No public API key is needed for this hosted checkout flow.

QR Ph is the default for the currently limited PayMongo account. Configure only methods actually enabled for your account: `qrph`, `gcash`, `paymaya` (Maya), or `card`, separated by commas. PayMongo presents payment-method selection; the app does not claim unavailable methods are supported.

After pushing reviewed backend changes to the branch tracked by Render, wait for Live. The Docker entrypoint runs `2026_10_13_120000_create_subscription_payments_table.php` before serving requests and rebuilds configuration/routes. Non-Docker deployments must run `php artisan migrate --force` and rebuild the config cache. No dependency installation or native app rebuild is required.

Register a **test-mode** webhook in PayMongo Developers / Webhooks:

- URL: `https://diskartech-backend.onrender.com/api/payments/paymongo/webhook`
- Event: `checkout_session.payment.paid`
- Copy that endpoint's signing secret into `PAYMONGO_WEBHOOK_SECRET` locally and in Render, then deploy the updated environment.

Without a configured webhook, payment still confirms when the employer returns to the subscription screen or selects **Check payment**. A webhook is needed to confirm payments while the app is closed.

## Payment flow and access

- Authenticated employer endpoints: `GET /api/employer/subscription`, `POST /api/employer/subscription/checkout`, and `POST /api/employer/subscription/payments/{uuid}/verify`.
- SQL `subscription_payments` stores server-derived account/price/cycle, request identity, provider session/payment references, and fulfillment dates. It never stores card numbers or wallet credentials.
- Student/household requests and other employers' payment records are rejected.
- The server creates V2 checkout sessions, passing its own reference and metadata, with a stable provider idempotency key. Unfinished checkout retries reuse the same record/session; switching billing duration is blocked while an unfinished checkout exists.
- Payment verification retrieves the session using the backend secret and checks ownership metadata, amount, PHP currency, test mode, paid status, and refunds/disputes. Closing the browser, a success redirect, or a client-supplied payment status never grants Premium.
- Webhooks require HMAC-SHA256 over the timestamp and raw body, use the test `te` signature, and reject timestamps older/newer than five minutes. Both documented webhook envelopes are supported. The handler retrieves the provider session before fulfillment.
- Transactions lock the employer and payment records in a consistent order. A paid record is fulfilled once, so repeated checks/webhooks cannot extend the plan twice. A late verified payment for an expired checkout is still credited. Paid renewals extend the stored expiry.
- Failed/time-out creation preserves the request/idempotency identity for retry. A checkout still preparing returns a retryable conflict. An unresolved creation older than 23 hours needs support/reconciliation before reuse because provider idempotency retention is 24 hours.
- Checkout redirects use a generic backend page. Close the browser to return to the app. **Resume checkout** and **Check payment** remain available for a pending checkout. If you need to abandon it and change duration, expire that unpaid session in the PayMongo dashboard first, then use Check payment; browser cancellation alone does not expire a session.

## Verification

Automated tests fake the provider; they do not make real PayMongo requests or charge anything.

```sh
php artisan test --compact --filter='EmployerSubscriptionTest|EmployerJobQuotaTest'
```

```sh
node --test scripts/test-employer-subscription.cjs scripts/test-employer-job-quota.cjs
npx tsc --noEmit
```

After deployment, log in as an employer, open the profile plan badge, select Premium and a duration, complete a simulated PayMongo payment, close the browser, and confirm Premium/expiry on both screens. Repeat Check payment: the expiry must remain unchanged. Test a canceled/failed payment: Basic must remain Basic. Test reopening a pending checkout and test the webhook with the app closed. No real provider round-trip is covered by the local automated tests.

References: [Hosted Checkout](https://docs.paymongo.com/docs/payment-channels-hosted-checkout), [retrieve sessions](https://docs.paymongo.com/re/reference/get_checkout_sessions), [idempotent requests](https://docs.paymongo.com/reference/idempotent-requests), [webhook signatures](https://docs.paymongo.com/do/docs/developer-tools-webhook-setup-management).
