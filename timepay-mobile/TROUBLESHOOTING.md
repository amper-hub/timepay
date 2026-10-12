# Mobile troubleshooting

- **API URL missing or invalid:** set `EXPO_PUBLIC_API_BASE_URL` in `.env.local`, restart Expo, and confirm the path has at most one `/api` suffix.
- **Phone cannot reach a local API:** use the development computer's LAN address and ensure both devices can reach each other. `localhost` on a phone refers to the phone.
- **Production rejects the URL:** production builds require the Railway API's HTTPS domain. Set the variable in the Expo build environment and rebuild.
- **Sign-in does not survive an app restart:** native Android/iOS uses Expo SecureStore; confirm the app is running as a native build and not the in-memory web preview.
- **Face verification unavailable:** check Laravel's `FACEPP_API_KEY` and `FACEPP_API_SECRET` in Railway variables. These are server-side only.

Use the repository [README](../README.md) for the complete setup and health-check procedure.
