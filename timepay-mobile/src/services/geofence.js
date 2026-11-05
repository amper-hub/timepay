// Keep this aligned with AttendanceController's existing server fallback.
const DEFAULT_COMPANY_GEOFENCE_RADIUS_METERS = 100;

const getDistanceMeters = (first, second) => {
  const earthRadiusMeters = 6371000;
  const toRadians = (value) => (value * Math.PI) / 180;
  const latitudeDelta = toRadians(second.latitude - first.latitude);
  const longitudeDelta = toRadians(second.longitude - first.longitude);
  const firstLatitude = toRadians(first.latitude);
  const secondLatitude = toRadians(second.latitude);
  const latitudeTerm = Math.sin(latitudeDelta / 2) ** 2;
  const longitudeTerm = Math.sin(longitudeDelta / 2) ** 2;
  const a = Math.min(
    1,
    latitudeTerm + Math.cos(firstLatitude) * Math.cos(secondLatitude) * longitudeTerm
  );

  return earthRadiusMeters * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
};

const classifyGeofence = (distanceMeters, accuracyMeters, radiusMeters) => {
  if (
    !Number.isFinite(distanceMeters) || distanceMeters < 0 ||
    !Number.isFinite(accuracyMeters) || accuracyMeters < 0 ||
    !Number.isFinite(radiusMeters) || radiusMeters <= 0
  ) {
    return "unavailable";
  }

  if (distanceMeters + accuracyMeters <= radiusMeters) return "inside";
  if (distanceMeters - accuracyMeters > radiusMeters) return "outside";
  return "unavailable";
};

const isLocationFresh = (timestamp, now = Date.now(), maxAgeMs = 120000) => {
  if (!Number.isFinite(timestamp) || !Number.isFinite(now) || !Number.isFinite(maxAgeMs) || maxAgeMs < 0) {
    return false;
  }

  const ageMs = now - timestamp;
  return ageMs >= 0 && ageMs <= maxAgeMs;
};

const classifyAttendanceLocation = (location, companyGeofence, now = Date.now()) => {
  const coordinates = location?.coords;
  const latitude = coordinates?.latitude;
  const longitude = coordinates?.longitude;
  const accuracy = coordinates?.accuracy;
  const siteLatitude = companyGeofence?.latitude == null || companyGeofence.latitude === ""
    ? NaN
    : Number(companyGeofence.latitude);
  const siteLongitude = companyGeofence?.longitude == null || companyGeofence.longitude === ""
    ? NaN
    : Number(companyGeofence.longitude);
  const radius = companyGeofence?.geofence_radius_meters == null ||
      companyGeofence.geofence_radius_meters === ""
    ? DEFAULT_COMPANY_GEOFENCE_RADIUS_METERS
    : Number(companyGeofence.geofence_radius_meters);

  if (
    !isLocationFresh(location?.timestamp, now) ||
    !Number.isFinite(latitude) || latitude < -90 || latitude > 90 ||
    !Number.isFinite(longitude) || longitude < -180 || longitude > 180 ||
    !Number.isFinite(accuracy) || accuracy < 0 ||
    !Number.isFinite(siteLatitude) || siteLatitude < -90 || siteLatitude > 90 ||
    !Number.isFinite(siteLongitude) || siteLongitude < -180 || siteLongitude > 180 ||
    !Number.isFinite(radius) || radius <= 0
  ) {
    return "unavailable";
  }

  const distance = getDistanceMeters(
    { latitude, longitude },
    { latitude: siteLatitude, longitude: siteLongitude }
  );

  return classifyGeofence(distance, accuracy, radius);
};

module.exports = {
  getDistanceMeters,
  classifyGeofence,
  isLocationFresh,
  classifyAttendanceLocation,
};
