# Mobile API URL configuration

Set the Laravel API root through Expo's `EXPO_PUBLIC_API_BASE_URL` variable. Do not edit a URL constant in source code.

1. Copy `.env.example` to `.env.local`.
2. Set the API root for the environment you are using.
3. Restart Expo after changing it. For native production builds, set the variable in the Expo build environment and rebuild the app.

Local development example:

```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.1.20:8000/api
```

Production value format:

```env
EXPO_PUBLIC_API_BASE_URL=https://<generated-api-domain>/api
```

The value may end with `/api` or the host root; the client normalizes it to exactly one `/api`. Plain HTTP is allowed only in Expo development. A missing or invalid value produces a readable configuration error. `EXPO_PUBLIC_*` values are embedded in the app and must never contain Face++ credentials, database passwords, or other secrets.

See the repository [README](../README.md) for development, production, and Railway instructions.
