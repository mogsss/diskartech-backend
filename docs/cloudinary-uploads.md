# Cloudinary uploads

New avatars are public Cloudinary images. School IDs, COR/COE, resumes, valid IDs and business permits are authenticated raw assets. Their original bytes remain available to the AI checker. The database stores permanent references, not expiring Cloudinary links.

Uploads create their folders automatically under `diskartech` (or the configured `CLOUDINARY_FOLDER`):

```text
diskartech/
├── student/
│   ├── coe/
│   ├── profile_pictures/
│   ├── resume/
│   └── school_id/
├── employers/
│   ├── avatar/
│   ├── certificates/
│   └── validID/
└── households/
    ├── avatar/
    └── validIDs/
```

The upload's `asset_folder` controls its location in the Cloudinary Assets console in **Dynamic folders** mode. Registration and profile uploads use the same Cloudinary folders. Existing Cloudinary assets are not automatically moved by this change; their saved references continue to work.

## Configure the backend

1. In the Cloudinary dashboard, find **Cloud name** under **Product Environment**. For this environment it is `pfe8kkrn`.
2. Click **Go to API Keys**. Copy the API key and its matching secret from that same product environment. If necessary, create a key named `DiskarTech Backend`.
3. Add these variables to `diskartech-backend/.env` for local development, and to **Render → backend service → Environment** for production:

   ```dotenv
   UPLOAD_STORAGE=cloudinary
   CLOUDINARY_CLOUD_NAME=pfe8kkrn
   CLOUDINARY_API_KEY=your_api_key
   CLOUDINARY_API_SECRET=your_api_secret
   CLOUDINARY_FOLDER=diskartech
   ```

   Keep the secret on the backend. Do not commit `.env`, use an `EXPO_PUBLIC_` variable for the secret, or paste it into chat.
4. In Cloudinary **Settings → Security**, enable **Allow delivery of PDF and ZIP files** if PDF delivery is blocked on the free plan. These documents still use authenticated delivery; they do not become unsigned public assets.
5. Locally run `composer install` and `php artisan config:clear`. Render installs Composer dependencies during its build and caches configuration at startup. Save the Render variables and deploy the backend changes, including `composer.lock`, the services, models, controllers, configuration and `routes/web.php`.
6. Refresh or restart the mobile app after deploying the backend. The frontend changes are JavaScript changes and do not require a new native development build.

`UPLOAD_STORAGE=public` remains the local-storage default for existing installations. A configured Cloudinary upload that fails returns an error; it never silently saves to Render's temporary disk.

## Preserve old uploads before deploying

Cloudinary cannot recover files already lost from Render. The database paths alone do not contain their contents. Re-upload missing documents or recover them from an existing backup.

**Before restarting or redeploying a Render service with local uploads**, back up its currently available `storage/app/public` files to persistent storage. A redeploy can remove those originals before the migration command gets a chance to read them. Local development's files are not automatically copies of production's files.

After restoring the originals to the backend's `storage/app/public` directory, run the migration against the database that owns those paths:

```powershell
php artisan uploads:migrate-cloudinary
php artisan uploads:migrate-cloudinary --write
```

The first command only previews. `--write` uploads available originals and updates each profile only if its old reference is still current. Missing files keep their database references and are reported. The command leaves the local originals in place, skips already migrated uploads on subsequent runs, and requeues unfinished AI document checks using the new references. Check any reported failures before retiring the old storage.

## Access and verification

- Avatars use HTTPS Cloudinary URLs.
- Protected documents use signed application URLs valid for one hour. The backend fetches the authenticated Cloudinary file and serves its bytes. Cloudinary signatures and API secrets are never included in client document responses.
- Profile owners and admins receive document links. Hirers receive resume links only for students who applied to their jobs; verification document links are omitted from other users' nested profile JSON.
- Mobile document previews fetch a fresh profile/application response before opening a file. **Retry preview** and **Open document** also request a new authorized link, so an expired cached link can be renewed without re-uploading. Protected PDFs open directly rather than sending their links to Google Docs Viewer.
- Mobile avatars, chat participants and job images share one URL resolver: full Cloudinary URLs retain their query strings, legacy paths receive one `/storage/` prefix, and signed `/media/` paths use the backend root. Raw private Cloudinary references are not public image URLs.
- Admin messages use current profile avatars from the database instead of stale Firebase chat snapshots. Legacy local avatar paths still resolve through backend storage.
- Legacy relative paths still work while their local originals exist.

After deployment, upload a profile picture and a PDF document from each account type. Confirm that Cloudinary shows the assets, mobile previews and admin **Open file** work, and the AI check completes. Restart the backend and verify the same Cloudinary uploads remain accessible. Backend delivery still uses Render bandwidth; storage and Cloudinary usage depend on the product environment's plan.

Automated coverage: `php artisan test --filter=CloudinaryUploadsTest` covers image/PDF byte delivery, signature restrictions, expired-link renewal, profile/avatar responses for all roles and admin avatar resolution. Run `node scripts/test-media-urls.cjs` in `DISKARTECH` for mobile URL handling. These tests mock the cloud network; a real upload with your configured credentials still needs the deployment check above.

Official references: [PHP SDK](https://cloudinary.com/documentation/php_integration), [authenticated assets](https://cloudinary.com/documentation/control_access_to_media), [PDF delivery settings](https://cloudinary.com/documentation/pdf_optimization).
