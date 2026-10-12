# Mobile network debugging

Use `EXPO_PUBLIC_API_BASE_URL` from `.env.local` to configure the API. The diagnostic helper reads the same variable:

```powershell
$env:EXPO_PUBLIC_API_BASE_URL = "http://192.168.1.20:8000/api"
node diagnose-network.js
```

Do not edit an API URL constant in source. For local physical-device development, the API host must be reachable over the LAN. For production, use the Railway API's public HTTPS URL and rebuild the app.

See the repository [README](../README.md) for setup and troubleshooting.
