# Admin custom domain on Render

The public website stays at `https://diskartech.world`. The same Render service
serves the admin console at `https://admin.diskartech.world`; no second backend
service or database is needed.

## DNS and HTTPS

1. In Namecheap, open Domain List → diskartech.world → Manage → Advanced DNS.
2. Keep the existing root A record and `www` CNAME. Add:

   | Type | Host | Value | TTL |
   | --- | --- | --- | --- |
   | CNAME Record | `admin` | `diskartech-backend.onrender.com` | 1 min |

3. In the existing Render backend service, open Settings → Custom Domains.
   Add `admin.diskartech.world`, save, and verify it.
4. Wait for Render to issue the HTTPS certificate before enabling `ADMIN_DOMAIN`.
   Adding the DNS alias alone does not select the admin login page.

Render currently includes two custom domains on Hobby. The root, automatic `www`,
and `admin` can use three entries; check the workspace's domain allowance and billing
before adding the third. See [Render custom domains](https://render.com/docs/custom-domains)
and [Namecheap DNS on Render](https://render.com/docs/configure-namecheap-dns).

## Enable the admin hostname

Deploy these code changes to the backend's `main` branch. Sign out of any existing
admin session on the old hostname first: host-only session cookies do not migrate
to the new hostname, and an existing login ownership lock lasts until logout or
idle expiry.

Once the domain and HTTPS certificate are ready, set these Render environment values
and deploy:

```dotenv
APP_URL=https://diskartech.world
ADMIN_DOMAIN=admin.diskartech.world
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
```

`ADMIN_DOMAIN` is a hostname without a scheme, slash, or port. `SESSION_DOMAIN=null`
keeps the session and admin ownership cookies scoped to the hostname that issued
them. Keep the current `APP_KEY` and persistent session driver.

The Docker entrypoint rebuilds configuration and route caches on deployment. For
other deployments, rebuild them with `php artisan config:cache` and
`php artisan route:cache` after changing the environment.

## Expected behavior

- Public `/` renders the landing page, including when an admin is signed in.
- Admin `/` opens the login page or dashboard, depending on authentication.
- Admin paths retain their existing `/admin/...` URLs on the new hostname.
- Public/Render `/admin`, `/admin/...`, and `/login` URLs return HTTP 404, without
  redirecting to the admin hostname. Unknown browser pages use the DiskarTech 404 page.
- The 404 page links back to the public homepage, or the admin homepage when viewed
  on the configured admin hostname. API requests expecting JSON keep JSON errors.
- Login and admin writes only execute on the admin hostname. Old pages with forms
  open before the switch should be refreshed before submitting.
- Authentication, CSRF protection, and single-browser session ownership still apply.
- The login page's return link opens the public `APP_URL`.
- API, subscription return, certificate, attachment, and media routes retain their
  existing paths. Keep the `onrender.com` URL enabled for installed mobile clients.

For localhost, leave `ADMIN_DOMAIN` unset and keep the existing `/admin/login` URL.
To roll back host routing, remove `ADMIN_DOMAIN` and redeploy; old host-scoped
sessions may require signing out or waiting for the session ownership lock to expire.

Validation: `php artisan test --filter="AdminDomainTest|AdminSessionTest|AdminDashboardTest"`.
