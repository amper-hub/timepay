# TimePay Expo app

This directory contains the React Native / Expo client. Its Laravel-looking `app/`, `artisan`, and Composer files are legacy scaffold files; Railway should deploy only `../timepay-backend`.

For prerequisites, local setup, API URL configuration, builds, and deployment, follow the repository [README](../README.md).

Set `EXPO_PUBLIC_API_BASE_URL` in `.env.local` for local development or in the Expo production build environment for distribution. The client appends exactly one `/api` path segment and requires HTTPS in production. Never put Face++ credentials or other secrets in an Expo public variable.

Attendance is online only. The mobile client checks Expo Network state before submitting; an explicit disconnected or unreachable result stops the upload. Unknown state or an unavailable connectivity check does not substitute for the API request, whose HTTP response remains authoritative. Attendance event metadata is stored securely and reused after ambiguous request failures, allowing the server idempotency key to prevent duplicate punches on retry. Metadata is cleared only after a successful response.
