# TimePay Clock-In, Face Verification, and Geofence Specification

Review date: 2026-10-24 (Asia/Manila)
Branch: `chore/dependency-security-audit`
Repository baseline: `9777d6f`; the worktree has pre-existing modified and untracked files. This document records the inspected worktree, not a clean checkout.

## Objective and constraints

Diagnose and correct only defects supported by code inspection, sanitized logs, or repeatable tests. Preserve authentication, company scoping, Face++ identity checks, server-side liveness, geofence coordinates/radius, event ID replay protection, private selfie storage, employee records, and all unrelated work. No production access, database connection, migration, real provider request, attendance request, or environment change is part of this task.

## Functional requirements

- **FR-1:** Distinguish transport failures from HTTP responses and local/client failures in sanitized development diagnostics.
- **FR-2:** Preserve authorization, company membership, liveness, identity, and idempotency checks for every attendance event.
- **FR-3:** Do not accept a stale or insufficiently accurate cached location as a verified current location after a GPS timeout.
- **FR-4:** Use the configured company coordinates and radius and retain Laravel's server-side distance validation. Do not change the radius or add an unsubstantiated threshold.
- **FR-5:** Keep capture/liveness rejection, missing baseline, identity mismatch, storage failure, provider outage, and unexpected errors distinguishable through safe responses/messages.
- **FR-6:** Never show raw exception/provider/database details, credentials, request bodies, coordinates, employee identifiers, or biometric data to users or diagnostics.
- **FR-7:** Retries of an ambiguous event retain its stable event ID and cannot create duplicate attendance records.
- **FR-8:** Do not change enrollment/reset semantics or employee/photo data as part of diagnostics.

## Diagnostic requirements

- **DR-1:** Correlate a failed mobile request with a Laravel request only from existing sanitized evidence; do not send a diagnostic request.
- **DR-2:** Distinguish DNS/routing, refusal, timeout, HTTP response, upload, and server exception when available.
- **DR-3:** Record a sanitized error category/status only; do not log request bodies or sensitive metadata.
- **DR-4:** Treat location timestamp/accuracy as evidence; never log exact employee coordinates.
- **DR-5:** Use mocked provider tests only. Do not call Face++ for diagnosis.

## Baseline findings before implementation

### Mobile flow and API

- `timepay-mobile/src/navigation/AppNavigator.tsx` mounts `Attendance` and `FaceVerification`. The active clock action passes through `timepay-mobile/src/screens/FaceVerification/index.js`.
- Both `timepay-mobile/.env` and `.env.development.local` resolve to `http://10.65.23.55:8001/api` (sanitized host only; no URL credentials). The local Wi-Fi adapter currently has IPv4 `10.65.23.55`.
- The mobile helper statically reads `process.env.EXPO_PUBLIC_API_BASE_URL` and normalizes its path to `/api`. The installed Expo SDK 54 `@expo/env` loader selects `.env.development.local` before `.env.local`, `.env.development`, and `.env` in development; an already-set process environment value is not overwritten. The inspected shell had no process override, `eas.json` contains no value for this variable, and `.env.local` is lower priority than `.env.development.local`. EAS-hosted environment values were not inspected.
- Port 8001 is listening on all IPv4 interfaces under PID 22580, executable `C:\wamp64\bin\php\php8.2.26\php.exe`. Windows did not provide this process's command line, so the Laravel project served by the process is **not verified**. No HTTP probe was made.
- `submitAttendancePunch` creates/loads one persisted event ID, sends the selfie plus all three liveness frames, and clears that event ID only after a successful HTTP response. Laravel stores replay responses scoped to the authenticated user and company. Existing tests cover duplicate and cross-employee replay.
- The API catch logs `response?.status` alone, which can appear as `undefined`. The response interceptor separately classifies response, request-without-response, and setup errors in development. The catch record by itself does not show whether an HTTP response arrived; it also covers local work such as event persistence.

### Location and geofence

- The location service waits up to 10 seconds for `Accuracy.Highest`, then returns Expo's last-known position when present. The active Face Verification screen displays a warning but still submits those coordinates.
- The active screen does not call the existing freshness/accuracy classifier. `HomeScreen` uses `isLocationFresh` (default maximum age 120 seconds) and `classifyGeofence` (the full reported accuracy circle must fit inside the configured radius for a definite inside result); that logic is not currently applied to the punch.
- Laravel validates latitude/longitude ranges and calculates distance to the authenticated employee's company coordinates. It accepts when the point's distance is within the configured company radius. The API payload does not include a GPS accuracy value or location-fix timestamp, so the server cannot independently assess those properties.
- The app's geofence endpoint returns company settings, but no database-backed company coordinates or radius were read for this investigation. The user's reported position relative to the site is therefore **not verified**.

### Face verification and errors

- The mobile screen captures a selfie and three blink frames, and the API helper uploads those files as multipart fields. Laravel requires the selfie and all three frames, validates blink evidence through Face++ and compares the reopened frame/selfie before enrollment or baseline comparison.
- Provider service tests fake every Face++ request. No real provider request was made.
- The Face Verification catch currently uses the `face` error context for every failure, including local location permission/acquisition errors. A plain local error may therefore receive a face-verification fallback message. Recognized `LocationTimeoutError` and Axios transport errors have more specific mappings.
- The current Laravel log file contains no `/attendance/clock-in`, `/attendance/store`, `face_liveness_failed`, `face_verification_unavailable`, or `selfie_storage_failed` matches. It does not establish whether the reported device request reached Laravel or what status/code it received.

### Local service and database identity

- Port 3306 is listening on `0.0.0.0` under PID 19192. The configured WAMP MySQL service was previously observed as PID 2812; the listener identity and isolation remain **unverified**. No database connection or query was made.
- The 8001 listener's PHP executable is identified, but its command line/project identity is not. The configured host address matches the mobile URL, but the API destination is not fully verified until the serving Laravel project is identified.

## Confirmed defects versus unresolved incident cause

1. **Confirmed location evidence gap:** the active attendance flow submits the last-known location after a timeout without checking its age, reported accuracy, or uncertainty against the company's geofence. This can make stale/uncertain evidence eligible for a server-accepted punch. It is not evidence that the reported punch failed for this reason.
2. **Confirmed diagnostics gap:** the attendance catch's `status: undefined` shape is ambiguous. The exact device log, request time, and correlation with a Laravel request are unavailable, so the actual incident's transport/HTTP/server cause is **not confirmed**.
3. **Confirmed error-context gap:** the face screen labels local location-stage failures with the face fallback. No specific local failure or Face++ result is correlated with the user's report.

## Acceptance criteria

- **AC-1:** Failure diagnostics use a numeric status for a confirmed HTTP response, `null` without a response, and a safe category for HTTP response, request-without-response, local/setup failure, or local failure after a completed HTTP response.
- **AC-2:** Attendance is not submitted with stale, invalid, or uncertainty-overlapping GPS evidence. The existing 120-second freshness window and full-accuracy-circle geofence classification are reused; Laravel remains authoritative for its existing point-in-radius check.
- **AC-3:** Company coordinates, radius, and Laravel server-side geofence policy are not weakened or changed.
- **AC-4:** Blink and Face++ identity checks remain required; enrollment does not record a punch.
- **AC-5:** Same-event retries reuse the same ID; no duplicate logs are created.
- **AC-6:** Safe UI messaging distinguishes location, face/provider, storage, and ordinary attendance failures.
- **AC-7:** Tests cover new diagnostic classification and location evidence decisions plus existing face/idempotency paths.
- **AC-8:** No database records or biometric photos change during diagnostics.
- **AC-9:** The acceptance checklist is updated with only verified evidence and outstanding blockers.
- **AC-10:** Test results and the absence of device/provider verification are explicit.

## Baseline verification (before implementation)

- `npm.cmd test` in `timepay-mobile`: **36 passed** at the earlier baseline (15 error mapping, 4 auth storage, 4 camera permissions, 5 geofence, 4 attendance queue, 4 attendance event). The queue tests were later removed as part of the online-only refactor; this is historical baseline evidence only.
- `npm.cmd run typecheck` in `timepay-mobile`: **passed**.
- `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty `DB_URL`; `php artisan test --testsuite=Feature --filter=AttendanceVerificationTest`: **18 passed, 144 assertions**.
- Same process-scoped SQLite override; `php artisan test tests/Unit/FacePlusPlusServiceTest.php`: **10 passed, 28 assertions**; provider requests are faked.
- Laravel tests emitted only an Xdebug log-file permission warning; test results were successful. No MySQL, production, attendance API, or real Face++ access occurred.

## Remaining verification blockers

- Identify PID 22580's command line/project root with an administrator before treating port 8001 as this Laravel app.
- Capture sanitized device failure time, Axios diagnostic category/status/code, and Laravel-side request/error evidence for one approved local test after database isolation is positively established.
- Verify the configured database listener PID 19192 and isolation before any local end-to-end attendance/reset test.
- Verify GPS freshness/accuracy and company geofence settings on a physical device without recording precise coordinates in ordinary logs.
- Real Face++ behavior, device liveness, production storage durability, backup/restore, and production environment remain outside this task and unverified.

## Implementation and verification — 2026-10-24

The documented baseline preceded these changes. The focused fixes are:

- The active Face Verification flow now fetches the authenticated employee's existing company geofence and applies the existing freshness window and accuracy-circle classifier before uploading the attendance event. Invalid, stale, uncertain, or definitely outside location evidence stops the submission. A last-known reading may proceed only when it is still fresh and its reported accuracy circle is entirely inside the configured boundary; the existing warning remains visible. If the endpoint returns a null radius, the client uses Laravel's existing 100 m fallback. Laravel's point-distance validation is unchanged.
- Location acquisition/permission/geofence errors now use location-safe messages, including distinct safe messages for uncertain GPS and a definite outside result. Provider, liveness, identity, storage, and ordinary attendance response mappings remain unchanged.
- Attendance failure diagnostics now report `http_response`, `request_without_response`, `local_or_setup`, or `local_after_http_response`, with status only when a response is confirmed and only a sanitized error code. Raw exception text, response bodies, request data, employee information, coordinates, and biometric content are excluded from this attendance diagnostic.

Post-change verification:

- `npm.cmd test`: **45 passed**.
- `npm.cmd run typecheck`: **passed**.
- With process-scoped SQLite `:memory:` settings and empty `DB_URL`, `php artisan test --testsuite=Feature --filter=AttendanceVerificationTest`: **18 passed, 144 assertions**.
- With the same isolated settings, `php artisan test tests/Feature/CompanyGeofenceApiTest.php`: **2 passed, 3 assertions**; the endpoint remains authenticated and company-scoped.
- With the same isolated settings and fake provider HTTP, `php artisan test tests/Unit/FacePlusPlusServiceTest.php`: **10 passed, 28 assertions**.
- `git diff --check`: no newly introduced whitespace errors; it reports the pre-existing blank line at `timepay-backend/.gitignore:26` and existing CRLF normalization warnings.

These tests establish code-level and mocked behavior only. The actual reported punch failure is still uncorrelated because there is no matching local Laravel log entry and the serving project for PID 22580 remains unidentified. No Android build or physical-device test was run for this change. No API request, MySQL connection, Face++ request, migration, or production operation was performed.

## Clock-in request failure diagnostics addendum — 2026-10-24

### Evidence

- The supplied device log reports `failureKind=local_or_setup`, no request object, no response, and no status/code. The current diagnostics helper emits only those fields, so that sample does not identify the original exception.
- `submitAttendancePunch` can fail before Axios sends a request while loading the stable event metadata or constructing multipart `FormData`; Axios can also reject in its request interceptor before creating a request. The catch rethrows the original error object unchanged, but its diagnostic output omits the error name/message, selected API base URL, endpoint/method, and current stage.
- Existing Axios interceptor logs include raw `error.message` and request URL strings. Those fields can contain local file/URI details or URL query values and need sanitization.
- Current `npm start` runs `expo start`. The installed Expo env loader selects `.env.development.local` before `.env.local` and `.env` for development, while an existing process variable is not overwritten. The current shell has no `EXPO_PUBLIC_API_BASE_URL` override; parsing with the installed loader resolves the local development target at `10.19.40.55:8000/api`. The dev-local file overrides the lower-priority Railway `.env.local` entry.
- React Native 0.81.5 FormData accepts `{uri, name, type}` file parts. The Android networking module passes the multipart type to OkHttp's multipart builder, which generates its boundary. The active screen requires all three blink-frame URIs before calling the API, and Laravel validates the selfie and three frame fields. Static code does not support changing the multipart header as the cause of this pre-request diagnostic.

### Minimal change and acceptance

- Keep attendance request construction, manual multipart header, stable event IDs, authentication, liveness, identity, geofence, and server behavior unchanged.
- In development diagnostics only, add an allowlisted error name/message category, sanitized API base URL, endpoint, method, and a fixed request stage (`event_metadata`, `form_data`, `http_request`, or `event_completion`). Strip URL credentials, all query/hash data, local file paths, and unknown free-form error messages. Never serialize Axios config, headers, body, FormData, or the original exception.
- Replace interceptor logging of raw messages/URLs with the same sanitized diagnostic formatter. Preserve the original thrown/rejected error object.
- Add focused unit cases for local/setup error, no-response request, HTTP status, post-response local failure, URL credential/query redaction, and omitted unclassified messages. Do not infer a root cause from the current sample; use the next sanitized diagnostic record to determine it.
- Do not send a live attendance request, query MySQL, or call Face++ while the serving project/database identity remains unverified. The cause remains unconfirmed until a new diagnostic record is correlated with a request/server log or reproducible local failure.
