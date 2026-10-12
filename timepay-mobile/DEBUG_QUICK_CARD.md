# Quick configuration card

- Local app URL: `EXPO_PUBLIC_API_BASE_URL=http://<reachable-dev-host>:8000/api`
- Production app URL: `EXPO_PUBLIC_API_BASE_URL=https://<generated-api-domain>/api`
- The environment variable is public and embedded at build time. Put no secrets in it.
- Change `.env.local`, restart Expo, and rebuild native production apps after changing the production URL.

Full instructions: [repository README](../README.md).
