export type GeofenceClassification = "inside" | "outside" | "unavailable";

export function getDistanceMeters(
  first: { latitude: number; longitude: number },
  second: { latitude: number; longitude: number }
): number;

export function classifyGeofence(
  distanceMeters: number,
  accuracyMeters: number,
  radiusMeters: number
): GeofenceClassification;

export function isLocationFresh(
  timestamp: number,
  now?: number,
  maxAgeMs?: number
): boolean;

export function classifyAttendanceLocation(
  location: {
    timestamp: number;
    coords: {
      latitude: number;
      longitude: number;
      accuracy: number | null;
    };
  },
  companyGeofence: {
    latitude: string | number | null;
    longitude: string | number | null;
    geofence_radius_meters: string | number | null;
  },
  now?: number
): GeofenceClassification;
