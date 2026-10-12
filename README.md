# TimePay

TimePay is an employee attendance, leave, and payroll application. The mobile client is React Native with Expo. The REST API and employer portal use Laravel 12, PHP 8.2+, and MySQL. Google ML Kit checks the live camera preview for a blink sequence; Laravel calls Face++ for server-side eye-state checks and identity comparison.

## Repository layout

- `timepay-backend/` — the deployable Laravel API and employer portal, its migrations, tests, and Railway configuration.
- `timepay-mobile/` — the Expo app. This directory also contains an old Laravel scaffold; the mobile app's `package.json`, `App.tsx`, and `src/` are the active client. Do not deploy the nested Laravel scaffold as a separate service.
- `README.md` — this repository's setup and deployment instructions.

The application does not contain a Python face service. Face++ credentials are used only by Laravel; Google ML Kit runs in the native mobile app.

## Clone

```bash
git clone https://github.com/amper-hub/timepay.git
cd timepay
```

## Requirements

- PHP 8.2 or newer, Composer 2, and the PHP extensions required by `composer.json` (including PDO MySQL, GD, DOM/XML, mbstring, and ZIP).
- MySQL 8.0 or a compatible Railway MySQL service.
- Node.js 22 and npm for Laravel Vite assets and Expo SDK 54.
- Android Studio/Android SDK for local Android builds. iOS builds require macOS and Xcode.

## Local setup

### Laravel API and portal

```powershell
cd timepay-backend
composer install
Copy-Item .env.example .env
```

Create a local MySQL database named `timepay`, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `timepay-backend/.env`. Generate a local key and apply migrations:

```powershell
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

The API is served under `/api`; the unauthenticated health endpoint is `/up`. Configure SMTP if local OTP email delivery is required. Set `FACEPP_API_KEY` and `FACEPP_API_SECRET` in the backend `.env` to exercise live Face++ calls. Never put these credentials in the mobile app or in an `EXPO_PUBLIC_*` variable.

### Expo mobile app

```powershell
cd timepay-mobile
npm ci
Copy-Item .env.example .env.local
```

Set `EXPO_PUBLIC_API_BASE_URL` in `timepay-mobile/.env.local` to the Laravel API root. For a physical phone, use the development computer's LAN address; for a simulator, use an address reachable from that simulator. The value may include `/api`; the client normalizes it to exactly one `/api` suffix.

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.1.20:8000/api
```

Start Expo with `npm start`. Local HTTP is accepted only in development builds. Native Android and iOS sessions are stored with Expo SecureStore (Android Keystore and iOS Keychain); the optional web development target keeps its session in memory only.

The ML Kit package uses native code, so run the app with a development build (`npm run android` or `npm run ios`) rather than Expo Go.

## Railway deployment

Deploy the Laravel API and MySQL as separate Railway services. Do not deploy Expo as a web service. The app needs the URL of the public Laravel API at native build time.

### Laravel service

1. Create a Railway project and add the GitHub repository `https://github.com/amper-hub/timepay.git`.
2. Create the Laravel service from that repository and set its **Root Directory** to `/timepay-backend`.
3. The backend has `railpack.json` with the PHP provider. Let Railway's PHP deployment provide PHP-FPM and Caddy; leave the Start Command unset so the generated web server binds to Railway's `PORT` and serves Laravel's `public` directory.
4. Set the service **Build Command** to `sh railway/build.sh`. Railpack installs the Composer and npm dependencies; this script builds the Vite assets and ensures Laravel cache/storage directories are writable.
5. Set the **Pre-deploy Command** to `sh railway/init-app.sh`. It runs only `php artisan migrate --force`; failed migrations stop deployment. It never wipes or recreates production tables.
6. Set the Railway **Healthcheck Path** to `/up`.
7. Generate a Railway public domain for this API service. Set `APP_URL` to the HTTPS origin (without `/api`). Set the mobile app's `EXPO_PUBLIC_API_BASE_URL` to that origin with `/api` appended.
8. Attach a persistent Railway volume to the Laravel service at `/app/storage/app`. Attendance photos and saved face enrollment images are files, not MySQL rows; without persistent storage they can disappear on redeploy or restart. Railway mounts volumes as root-owned, so verify PHP can write to the mounted path after deploy. If the Railpack runtime uses a non-root UID, set `RAILWAY_RUN_UID=0` or configure writable ownership for the mount; the build script's permissions do not carry onto a runtime volume. Back up the volume and restrict access to the service.

The current deployment uses Railpack's PHP runtime rather than a custom Dockerfile. The service runs Railway's managed PHP-FPM/Caddy start strategy. No custom `PORT` value is needed; Railway supplies it. The repository does not contain a Railway project/domain, so the public URL must be added after Railway generates it.

### MySQL service and variables

Add a Railway MySQL service and map its service reference variables to the Laravel names on the API service:

| Laravel variable | Railway MySQL variable |
| --- | --- |
| `DB_CONNECTION` | literal `mysql` |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |

Replace `MySQL` in the references with the exact Railway service name if it differs. Keep MySQL private to the Railway project. Laravel defaults to MySQL when `DB_CONNECTION` is missing, rather than silently selecting SQLite.

### Required Laravel service variables

Set these in Railway's variable manager; do not commit production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` — generate once with `php artisan key:generate --show` from `timepay-backend`, then store the output as the Railway variable. Keep the same key across restarts and deployments.
- `APP_URL=https://<generated-api-domain>`
- `DB_CONNECTION=mysql`, and the five mapped `DB_*` variables above.
- `LOG_CHANNEL=stderr` and `LOG_LEVEL=info` so application logs reach Railway.
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, and `QUEUE_CONNECTION=sync`. The selfie audit job runs inline so it can read the API service's volume without a separate worker.
- `FILESYSTEM_DISK=local` (private local storage).
- `FACEPP_API_KEY` and `FACEPP_API_SECRET` from the Face++ console. Optional `FACEPP_COMPARE_URL`, `FACEPP_DETECT_URL`, `FACEPP_CONFIDENCE_THRESHOLD`, and `FACEPP_EYE_CONFIDENCE_THRESHOLD` are documented in `.env.example`.
- SMTP variables if production email verification or password-reset OTP email is enabled. The example `.env` uses the log mailer and does not deliver email.

Laravel's session, cache, and application data are stored in MySQL. Uploaded selfie and baseline image files are stored under `storage/app/private` on the Railway volume. Configure and test database and volume backups before accepting production attendance data.

### Expo production builds

Set `EXPO_PUBLIC_API_BASE_URL` to the actual generated Railway HTTPS origin plus `/api` in the Expo build environment. It is a public endpoint, not a secret. Example format only:

```env
EXPO_PUBLIC_API_BASE_URL=https://<generated-api-domain>/api
```

The URL is embedded when the native app is built; changing the Railway domain requires rebuilding the app. Production builds reject plain HTTP and fail with a readable configuration message if the URL is missing or malformed. The repository has no EAS project or production build profile configured yet, so create/configure the Expo Application Services project and its Android/iOS signing credentials before store distribution.

## Face verification and data handling

- ML Kit runs on-device and looks for exactly one face and an open → closed → open blink sequence. The three samples are uploaded with the final selfie.
- Laravel requires all four images, rechecks each blink state through Face++ `detect`, and verifies the final selfie matches the last blink frame before enrollment or attendance processing. A client-supplied Boolean is not accepted as liveness evidence.
- For an enrolled user, Laravel calls Face++ `compare` against the saved baseline and applies `FACEPP_CONFIDENCE_THRESHOLD`. Provider errors fail closed and do not create an attendance log. Identity mismatches are recorded as flagged for employer review, preserving the existing review behavior.
- The blink check is a basic challenge-response signal, not strong presentation-attack detection. No server-side camera attestation or validated anti-spoofing model is implemented. Test on supported physical devices and assess spoofing risk before relying on this for high-assurance attendance.
- Images are needed for the identity comparison, enrollment, and current audit workflow. New images are stored under `storage/app/private` and returned through an authenticated API route limited to the employee, their company administrators, or a super administrator. Legacy image files are read from either private or public storage, but the deployment does not create a public storage link. Set and verify a retention/deletion policy for biometric files before production.

### Operational biometric image retention policy

This is an operational policy proposal, not a statement of legal compliance. The company operating TimePay must choose and publish a numeric attendance-image review period before accepting production biometric images.

- Keep an enrollment baseline only while face verification is enabled for that employee. Delete the file and clear its database reference when the employee resets facial enrollment, opts out, or is offboarded.
- Keep an attendance proof image only through the company-defined attendance review/dispute period. At expiry, delete the file and its database reference while retaining only the attendance metadata still needed for payroll and audit.
- Use blink samples only for the verification request; do not retain them as separate permanent files.
- Limit image access to the authenticated employee and authorized company or platform administrators. Include volume snapshots and backups in the deletion schedule.
- The current code has no scheduled image purge, and account deletion does not remove stored biometric files. Implement and test those deletion paths before treating this policy as active.

## Migrations, tests, and builds

Run local migrations with `php artisan migrate`. Railway runs `php artisan migrate --force` from the pre-deploy command. Review each new migration before production; never run `migrate:fresh` or `db:wipe` against production data. A backup is recommended before schema changes.

Backend checks (from `timepay-backend/`):

```powershell
composer check-platform-reqs
php artisan route:list --path=api
composer test
```

Mobile checks and local builds (from `timepay-mobile/`):

```powershell
npm test
npm run typecheck
npm run android
```

`npm run ios` is available on macOS with Xcode. The package's `npm run web` starts Expo's development web target; it is not a deployed web app. Production Android/iOS distribution needs Expo EAS configuration, app signing, and the production API URL described above.

## Health checks and troubleshooting

- Open `https://<generated-api-domain>/up`; it should return HTTP 200 without requiring a login.
- A Railway 502 or failed healthcheck usually means the PHP service did not start, `PORT` was overridden, or the wrong root directory was selected. Leave Start Command unset and inspect Railway deployment logs.
- Database connection errors: verify all five Railway MySQL references, `DB_CONNECTION=mysql`, and that API and MySQL services are in the same Railway project/environment.
- Missing styles or images: verify `sh railway/build.sh` completed and the volume is mounted at `/app/storage/app`.
- Mobile API configuration errors: verify the Expo build environment contains the public HTTPS API origin with `/api`, then rebuild the app.
- Face checks returning temporary-unavailable: verify server-side Face++ credentials, account/API access, timeout/rate limits, and Railway egress from the API service. Do not put credentials in the mobile build.

## Current deployment limitations

- Railway services, MySQL, domain, secrets, volume, backups, and Expo signing are not provisioned by this repository change.
- CI workflows are not present in the repository.
- Physical-device ML Kit/blink testing and live Face++ testing require devices and credentials unavailable in a local automated test run.
- `npm audit` still reports advisories in the pinned Expo SDK 54/React Native 0.81 build toolchain. The critical `shell-quote` issue and compatible Axios/PostCSS/transitive fixes are patched; remaining advisories include high findings with no in-range fix. Do not upgrade Expo or React Native independently of the SDK compatibility matrix.
- A disposable MySQL instance was unavailable, so Railway's MySQL migrations and mounted-volume permissions remain unverified in that runtime.
- The biometric image retention period and deletion process have not been set. This remains a production privacy blocker.
