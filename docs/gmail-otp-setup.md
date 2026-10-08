# Gmail OTP setup

DiskarTech's OTP endpoint uses Gmail API over HTTPS. `MAIL_MAILER`, Resend credentials, Gmail passwords, and Gmail App Passwords do not configure this endpoint. Sender: `diskartech.official@gmail.com`.

The integration is ready in the source code. Actual sending requires the Google authorization below and deployment of these changes to the backend.

## 1. Create the Google project

1. Sign in to [Google Cloud Console](https://console.cloud.google.com/) with `diskartech.official@gmail.com`.
2. Open the project selector, choose **New Project**, and name it **DiskarTech OTP**. Select the new project.
3. Go to **APIs & Services > Library**, search for **Gmail API**, and click **Enable**.

## 2. Configure authorization

1. Open **Google Auth platform > Branding > Get Started**.
2. App name: **DiskarTech OTP**. Support and contact email: `diskartech.official@gmail.com`.
3. Choose **External** as the audience, review Google's terms, and create the configuration.
4. In **Audience > Test users**, add `diskartech.official@gmail.com`.
5. In **Data Access > Add or remove scopes**, add only:

   ```text
   https://www.googleapis.com/auth/gmail.send
   ```

Only the sender authorizes access. People registering with DiskarTech receive ordinary emails; they do not need Google Cloud accounts or OAuth test-user entries. See [Google's consent configuration guide](https://developers.google.com/workspace/guides/configure-oauth-consent).

## 3. Create the OAuth client

1. Open **Google Auth platform > Clients > Create client**.
2. Application type: **Web application**. Name: **DiskarTech OTP backend**.
3. Under **Authorized redirect URIs**, add exactly:

   ```text
   https://developers.google.com/oauthplayground
   ```

4. Create the client and save the **Client ID** and **Client secret** privately. Google may show the secret only at creation. [Official client setup](https://developers.google.com/identity/protocols/oauth2/web-server).

## 4. Authorize the sender and obtain a refresh token

1. Open [Google OAuth Playground](https://developers.google.com/oauthplayground/).
2. Open the settings gear. Keep **Google** endpoints and **Server-side** flow. Select **Use your own OAuth credentials**, and enter your client ID and client secret.
3. Set **Access type: Offline** and **Force prompt: Consent Screen**.
4. In Step 1, enter `https://www.googleapis.com/auth/gmail.send` and click **Authorize APIs**.
5. Sign in specifically as `diskartech.official@gmail.com`, confirm the project is yours, and authorize sending email.
6. In Step 2, click **Exchange authorization code for tokens**. Save the **refresh token** privately, alongside the client credentials. Use the refresh token, not the short-lived access token.

## 5. Configure the backend

For local development, put these values in the backend's ignored `.env`. For the live backend, enter them in the Render service's **Environment** settings:

```dotenv
GMAIL_CLIENT_ID=your-client-id
GMAIL_CLIENT_SECRET=your-client-secret
GMAIL_REFRESH_TOKEN=your-refresh-token
GMAIL_FROM_ADDRESS=diskartech.official@gmail.com
OTP_RESEND_COOLDOWN=60
```

Keep credentials in backend environment settings. Do not put them in the Expo app, git, screenshots, or chat. The sender address must match the account that authorized the refresh token.

Deploy the updated backend after adding its environment settings. The existing Docker entrypoint caches configuration on startup. For a local backend with previously cached configuration, run `php artisan config:clear` after updating `.env`.

The frontend currently calls the Render backend, so setting only the local backend `.env` will not enable OTP for the app. Restart Expo for local development changes; distribute a new frontend build for installed production apps.

## 6. Verify delivery

Register using a second email address you control. Confirm the code arrives in its inbox or spam folder, enter that code, and confirm verification succeeds. Resend should count down for 60 seconds. A provider failure returns HTTP 503 and does not show “OTP Sent”; cooldown returns HTTP 429 with `retry_after` and `Retry-After`. Check Render logs for safe Gmail authorization or HTTP-status diagnostics if sending fails.

The automated tests use fake Google responses. They do not send real emails and cannot establish inbox delivery.

## Ongoing use

An External OAuth project in **Testing** generally issues Gmail refresh tokens that expire after seven days. Before relying on continuous operation, review **Audience > Publishing status**, move to production when appropriate, complete any verification Google requires, and authorize again to obtain a token under that status. Publishing does not remove all possible causes of token expiration or revocation. [Google token expiration rules](https://developers.google.com/identity/protocols/oauth2#expiration).

A free Gmail account has sending limits, commonly 500 emails per day; resends and other email sent from the account count too. The per-user cooldown reduces repeated requests but is not a global daily cap. Gmail still enforces its own limits. [Gmail sending limits](https://support.google.com/mail/answer/22839).
