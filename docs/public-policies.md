# Public policy pages

These public, server-rendered pages require no login and work without JavaScript:

- `https://diskartech.world/privacy-policy`
- `https://diskartech.world/terms-and-conditions`
- `https://diskartech.world/cookie-policy`

The landing footer links to all three. `resources/views/legal/policy.blade.php` supplies the Poppins layout, readable sections, section navigation, related policy links, and support email. It uses the same Tailwind CDN as the landing page; policy text is still present in the HTML when styling scripts are unavailable.

`resources/data/legal-policies.json` is a snapshot of the existing documents exported from the sibling mobile repository's `DISKARTECH/data/legalPolicies.ts` on October 16, 2026. The privacy copy includes the accepted deletion and retained-record disclosures. The backend has no runtime dependency on the mobile checkout or Node.

When policy wording changes, update the corresponding mobile document and JSON snapshot together. Preserve the document's actual updated date. `config/legal.php` loads the snapshot; Render rebuilds its configuration cache on deployment, so source changes require deployment. All text is escaped by Blade.

The October 21, 2026 Terms revision clarifies direct salary payments outside the app,
their separation from Pro subscription payments, and applicable employer obligations
for mandatory benefits. DiskarTech does not administer payroll or statutory
contributions; this feature limit does not waive legal rights or obligations.
The mobile consent gate uses the Terms updated date, so this revision requires a
new agreement once users receive it.

Official references used for the mandatory-benefits wording:

- [SSS compulsory coverage](https://www.sss.gov.ph/compulsory-coverage/)
- [SSS household employers](https://www.sss.gov.ph/household-employer/)
- [PhilHealth employer payment and reporting](https://www.philhealth.gov.ph/partners/employers/pay_procedures.php)
- [Pag-IBIG coverage and employer contributions under RA 9679](https://elibrary.judiciary.gov.ph/thebookshelf/showdocs/2/19155)

For Google Auth Platform > Branding, use the public homepage `https://diskartech.world`, Privacy Policy URL above, and Terms URL above. Authorized domains use `diskartech.world` without a URL scheme or path. Publishing the OAuth app still requires Google's remaining configuration/verification steps. Moving publishing status does not repair an already-invalid Gmail refresh token: reauthorize the sender using the same Web OAuth client and privately replace `GMAIL_REFRESH_TOKEN` in Render.

Run `php artisan test --filter=PublicPoliciesTest` to check anonymous access, full content, escaping, related/landing links, and route boundaries.
