# LiveKit online interviews

Online interview scheduling now needs a date and time only. Walk-in interviews retain their venue field. Employer/household applicant details and student application details show **Join video call** for online interviews with status `interview`.

The separate `/interview-call` mobile screen requests a short-lived token from `POST /api/applications/{id}/interview-call/token`. Sanctum authentication is required. Only the assigned student and the job owner (employer/household) can receive a token. Pending, accepted, hired, rejected, cancelled, completed, terminated and walk-in applications cannot use this endpoint.

Each application/schedule uses its own room. The backend chooses both room and participant identity; the client supplies only the application ID. Rescheduling switches to another room. Issued tokens are valid for initial connection for 10 minutes; changing an application status does not forcibly disconnect an already connected call or instantly revoke a previously issued token. Leave exits the current user's call and does not mark the interview as completed, hire the student or disconnect the other participant.

## Backend configuration

Set the following privately in local `.env` for local development, and separately in **Render → backend service → Environment** for the deployed app:

```env
LIVEKIT_URL=wss://your-project.livekit.cloud
LIVEKIT_API_KEY=your_project_api_key
LIVEKIT_API_SECRET=your_project_api_secret
```

Keep the API secret on the backend. Use a replacement key if a secret was exposed. The mobile app's API base URL currently points to Render, so editing the local `.env` does not configure the running Render service.

Deploy the backend changes, including `composer.json` and `composer.lock`. The existing Docker build installs `firebase/php-jwt` through Composer. No new database migration is required. Laravel's existing interview columns are reused; online venue text is stored as `DiskarTech video call`. With local cached configuration, run `php artisan config:clear` after changing environment values. For Render, save the environment values and redeploy/restart the service using its normal deployment flow.

## Mobile build

The native SDK and Expo plugins require a fresh development APK. Expo Go or an older APK cannot perform these calls. From the frontend repository:

```powershell
npx.cmd eas-cli build --platform android --profile development
npx.cmd expo start --dev-client --clear
```

Install that build on both phones and open the app through the development client. See the [LiveKit Expo guide](https://docs.livekit.io/transport/sdk-platforms/expo/).

## Two-device verification

1. Use an employer/household and a student with an existing application.
2. Invite the student: choose **Online**, pick the date and time, and send. No meeting link is required.
3. Open that application's details on each phone. Both should see the **Join video call** button.
4. Join on both phones. Tokens are generated automatically; dashboard-generated test tokens are not needed for this interview flow.
5. Allow camera and microphone access and confirm both participants can see/hear each other.
6. Test mute, camera off/on, leave, hardware Back, reconnect and permission denial. Camera/microphone access should stop after leaving.
7. Check that a walk-in interview still displays its venue/directions and no video-call button.

Backend tests verify token signature, grants, participant permissions, room pairing, rescheduling, invalid states and scheduling validation. Local simulated frontend checks cover platform guards and resource cleanup; a real call must still be verified on the new APK with the deployed backend. Provider outages/quota limits can cause connection failures and are shown through the app's NotificationModal.

References: [LiveKit tokens and grants](https://docs.livekit.io/frontends/reference/tokens-grants/), [LiveKit Expo setup](https://docs.livekit.io/transport/sdk-platforms/expo/).
