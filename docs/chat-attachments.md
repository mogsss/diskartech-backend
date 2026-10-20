# Chat photos and files

The mobile chat's plus button opens Photo and File choices. One attachment can be selected per message, with a preview, optional caption, remove action, and upload progress. JPG, PNG, WEBP, GIF, PDF, and DOCX are supported up to 10 MB. Server validation inspects the actual bytes, bounds image dimensions, verifies DOCX archive contents, and sanitizes filenames. No executable, HTML, SVG, or arbitrary ZIP attachments are accepted.

The file is uploaded only when Send is pressed. A failed message send preserves the composer draft and uploaded attachment ID for retry. Firestore writes the message and conversation preview in one batch. Retrying an unchanged draft uses the same message ID; message lists and push notifications use Photo or File: filename as the preview for an attachment-only message.

DOCX inspection uses `ZipArchive` so extensionless Linux/PHP temporary upload paths are supported. It reads archive metadata without extracting the document and retains the entry-count, expanded-size, required Word-entry, and macro checks. The Docker image already installs PHP's `zip` extension; local PHP installations must enable it too. Attachment validation errors are shown directly in the mobile error modal while the draft is retained.

## Storage and access

Production reuses `UPLOAD_STORAGE=cloudinary` and the existing Cloudinary credentials. Attachments are authenticated raw assets under `diskartech/chats/<chat_id>`. Their ownership and permanent storage reference are saved in SQL, while Firestore stores only the attachment UUID, filename, MIME type, kind, and size. Cloudinary delivery URLs, keys, and temporary application URLs are never stored in a message.

Local development with `UPLOAD_STORAGE=public` puts chat attachments on the private `local` disk, outside `storage/app/public`. The public storage route cannot access them.

`POST /api/chats/{chatId}/attachments` uploads a file and returns permanent attachment metadata. `GET /api/chats/{chatId}/attachments/{id}` authorizes the caller and issues a viewing link valid for 10 minutes. Files are served through the signed `/chat-attachments/{id}/view` route with safe MIME/disposition headers. The signed viewing link is a temporary bearer capability and should be treated like the document itself. Photos refresh their link when opened or after a delivery error; files request a fresh link before opening in the browser.

Authorization uses the app's existing canonical direct-chat IDs, `chat_u<lower_user_id>_u<higher_user_id>`, and the authenticated Laravel user. Both IDs must exist as mobile users. Upload ownership is derived by the server; client sender/recipient fields are ignored. Attachment lookup also checks the SQL chat ID and owner/recipient records. A copied or forged Firestore attachment ID cannot grant access to another conversation's file. Legacy chats with noncanonical IDs can continue sending text; attachment upload requires reopening a current direct conversation.

## Deployment

Work is on the `development` branch. Deploy the backend version containing these routes and migration before testing the feature against Render. The Docker entrypoint runs the required migration and rebuilds route/config caches. No new credentials or dependency installation is needed.

For a non-Docker deployment:

```sh
php artisan migrate --path=database/migrations/2026_10_12_160000_create_chat_attachments_table.php --force
```

The image/document picker modules are already included in the mobile app. Reload the development app after the backend deployment. Test with two accounts: send a photo or PDF, open it as the recipient, and verify that an unrelated account cannot retrieve the attachment. Uploads are rate limited; failed Firestore writes may leave an unreferenced private upload, so abandoned uploads can be removed during storage maintenance.

Existing Firestore rules still govern chat messages. This change authorizes attachment storage/viewing separately and does not deploy Firebase rules or change the Firebase authentication model.

Admin message history shows a Photo/File label for attachment-only messages. File viewing links are issued to the mobile sender/recipient; the admin screen does not receive private attachment URLs.

## Verification

```sh
php artisan test --compact --filter='ChatAttachmentsTest|CloudinaryUploadsTest'
```

```sh
node --test scripts/test-chat-attachments.cjs scripts/test-chat-screen.cjs scripts/test-chat-attachment-view.cjs
npx tsc --noEmit
```
