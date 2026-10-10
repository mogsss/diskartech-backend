# LiveKit online interviews

Online interview scheduling needs a date and time only, interpreted in **Asia/Manila (Philippine time)**. Walk-in interviews retain their venue field. Employer/household applicant details and student application details show **Join video call** for online interviews with status `interview`. Join is disabled before the scheduled instant and after the employer/household ends the interview. No automatic duration is assumed.

The `/online-interview-call` screen replaces the manual video-call demo and the previous `/interview-call` mobile route. It requests a short-lived token from `POST /api/applications/{id}/interview-call/token`. Sanctum authentication is required. Only the assigned student and the job owner (employer/household) can receive a token. The backend independently enforces the scheduled start and ended flag; a direct screen/API request cannot bypass the disabled button. Pending, accepted, hired, rejected, cancelled, completed, terminated and walk-in applications cannot use this endpoint.

Each application/schedule uses its own room. The backend chooses both room and participant identity; the client supplies only the application ID. Rescheduling switches to another room. Issued tokens are valid for initial connection for 10 minutes. The other participant occupies the large video frame on either role's phone; the local camera is a small mirrored preview. Camera-off and waiting placeholders preserve that layout.

**Leave** only exits the current user's call, allowing rejoin while the interview is active. **End Interview** is available to the assigned employer/household and requires confirmation. `POST /api/applications/{id}/interview-call/end` persists `interview_ended_at`, blocks new tokens, and calls LiveKit's DeleteRoom to disconnect participants. Its administrative JWT stays on the backend. Ending is idempotent and does not hire, reject, or mark the employment/application as completed. A changed interview schedule clears the ended flag; saving an unchanged schedule does not reopen it. The supplied room name must match the current schedule so an old call cannot end a rescheduled interview.

`GET /api/applications/{id}/interview-call` is permission-checked and returns server time, an ISO start timestamp with timezone, room name and availability. Detail buttons and connected screens refresh every five seconds while visible; details also update against the server time offset each second. A closed, inactive or rescheduled interview disconnects the app's current call. If LiveKit room deletion fails, the persisted closure still blocks new joins and the clients exit after the next successful availability poll. Already issued JWTs are not themselves revoked by a database update.

## Backend configuration

Set the following privately in local `.env` for local development, and separately in **Render → backend service → Environment** for the deployed app:

```env
LIVEKIT_URL=wss://your-project.livekit.cloud
LIVEKIT_API_KEY=your_project_api_key
LIVEKIT_API_SECRET=your_project_api_secret
```

Keep the API secret on the backend. Use a replacement key if a secret was exposed. The mobile app's API base URL currently points to Render, so editing the local `.env` does not configure the running Render service.

Deploy the backend changes, including `composer.json`, `composer.lock` and migration `2026_10_10_160000_add_interview_ended_at_to_job_applications.php`. Run `php artisan migrate --force` on deployment (the existing Docker startup runs migrations). The new nullable timestamp stores interview closure; the existing schedule columns remain in use. The Docker build installs `firebase/php-jwt` through Composer. Online venue text is stored as `DiskarTech video call`. With local cached configuration, run `php artisan config:clear` after changing environment values. For Render, save the environment values and redeploy/restart the service using its normal deployment flow.

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
3. Open that application's details on each phone. Join must remain disabled until the scheduled date/time, then enable on both phones without reloading.
4. Join on both phones. Tokens are generated automatically; dashboard-generated test tokens are not needed for this interview flow.
5. Allow camera and microphone access and confirm both participants can see/hear each other. The student must be large on the employer/household phone, and the employer/household large on the student phone; each self-preview must be small.
6. Test mute, camera off/on, leave, hardware Back, reconnect and permission denial. Camera/microphone access should stop after leaving.
7. Check that a walk-in interview still displays its venue/directions and no video-call button.
8. End the interview from the employer/household phone. Both calls should exit and Join should stay disabled after reopening the details. Students must not have End Interview.
9. Change the interview schedule: Join stays disabled before the new start and becomes available at that time. Leaving alone must not disable rejoin.

Backend tests verify token signature, grants, participant permissions, room pairing, rescheduling, invalid states and scheduling validation. Local simulated frontend checks cover platform guards and resource cleanup. On October 10, 2026, the user confirmed working calls; supplied device logs show both participants joining the same room and publishing camera/microphone tracks. Provider outages/quota limits can cause connection failures and are shown through the app's NotificationModal.

## Troubleshooting invalid tokens

The project URL, API key and API secret must belong to the same LiveKit project. Leading/trailing whitespace in the configured URL, key and secret is normalized before signing; this resolved the rejected tokens observed during testing. A changed Render environment value must be applied to the running deployment before testing again.

Temporary token-validation probes and configuration diagnostics have been removed. LiveKit and WebRTC informational/debug logs are disabled; warnings/errors remain available for actual connection or media failures. No tokens or secrets are logged by the call screens.

References: [LiveKit tokens and grants](https://docs.livekit.io/frontends/reference/tokens-grants/), [LiveKit Expo setup](https://docs.livekit.io/transport/sdk-platforms/expo/), [Room service API / DeleteRoom](https://docs.livekit.io/reference/other/roomservice-api/).
