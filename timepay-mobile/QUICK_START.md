# Quick start

1. Install Node.js 22 and npm.
2. From this directory, install packages with `npm ci`.
3. Copy `.env.example` to `.env.local` and set `EXPO_PUBLIC_API_BASE_URL` to the Laravel API reachable from your device.
4. From `../timepay-backend`, follow the root README to configure MySQL and run Laravel.
5. Run `npm start` here and open the app with an iOS or Android development client.

The active attendance screen uses Google ML Kit blink detection and submits the blink samples and selfie to Laravel for server-side Face++ checks. Use a physical device to verify camera behavior.

See the repository [README](../README.md) for full setup and deployment instructions.
