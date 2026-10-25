const { test } = require("node:test");
const assert = require("node:assert/strict");
const {
  getDistanceMeters,
  classifyGeofence,
  isLocationFresh,
  classifyAttendanceLocation,
} = require("../src/services/geofence.js");

test("distance is zero for matching workplace and device coordinates", () => {
  assert.equal(
    getDistanceMeters(
      { latitude: 14.5995, longitude: 120.9842 },
      { latitude: 14.5995, longitude: 120.9842 }
    ),
    0
  );
});

test("distance uses meters for nearby coordinates", () => {
  const distance = getDistanceMeters(
    { latitude: 0, longitude: 0 },
    { latitude: 0.001, longitude: 0 }
  );

  assert.ok(distance > 110 && distance < 112);
});

test("classifies only GPS circles fully inside or outside the company radius", () => {
  assert.equal(classifyGeofence(30, 10, 50), "inside");
  assert.equal(classifyGeofence(70, 10, 50), "outside");
  assert.equal(classifyGeofence(50, 10, 50), "unavailable");
});

test("returns unavailable for invalid distance, accuracy, or radius", () => {
  assert.equal(classifyGeofence(Number.NaN, 1, 50), "unavailable");
  assert.equal(classifyGeofence(10, -1, 50), "unavailable");
  assert.equal(classifyGeofence(10, 1, 0), "unavailable");
});

test("accepts only recent, non-future GPS readings", () => {
  assert.equal(isLocationFresh(88000, 100000), true);
  assert.equal(isLocationFresh(1, 130000), false);
  assert.equal(isLocationFresh(100001, 100000), false);
  assert.equal(isLocationFresh(Number.NaN, 100000), false);
});

test("attendance location is inside only when fresh and its accuracy circle fits", () => {
  const location = {
    timestamp: 99000,
    coords: { latitude: 0, longitude: 0, accuracy: 5 },
  };
  const geofence = {
    latitude: 0,
    longitude: 0,
    geofence_radius_meters: 50,
  };

  assert.equal(classifyAttendanceLocation(location, geofence, 100000), "inside");
  assert.equal(
    classifyAttendanceLocation(
      { ...location, coords: { ...location.coords, accuracy: 51 } },
      geofence,
      100000
    ),
    "unavailable"
  );
});

test("attendance location rejects stale and future GPS readings", () => {
  const geofence = { latitude: 0, longitude: 0, geofence_radius_meters: 50 };
  const location = { timestamp: 1, coords: { latitude: 0, longitude: 0, accuracy: 5 } };

  assert.equal(classifyAttendanceLocation(location, geofence, 130000), "unavailable");
  assert.equal(
    classifyAttendanceLocation({ ...location, timestamp: 100001 }, geofence, 100000),
    "unavailable"
  );
});

test("attendance location rejects invalid accuracy or missing company settings", () => {
  const location = {
    timestamp: 99000,
    coords: { latitude: 0, longitude: 0, accuracy: null },
  };

  assert.equal(
    classifyAttendanceLocation(location, { latitude: 0, longitude: 0, geofence_radius_meters: 50 }, 100000),
    "unavailable"
  );
  assert.equal(classifyAttendanceLocation(location, null, 100000), "unavailable");
  assert.equal(
    classifyAttendanceLocation(
      { ...location, coords: { ...location.coords, accuracy: 5 } },
      { latitude: 0, longitude: 0, geofence_radius_meters: null },
      100000
    ),
    "inside"
  );
});

test("attendance location reports a definite outside result only when uncertainty is outside", () => {
  const location = {
    timestamp: 99000,
    coords: { latitude: 0.01, longitude: 0, accuracy: 5 },
  };
  const geofence = { latitude: 0, longitude: 0, geofence_radius_meters: 50 };

  assert.equal(classifyAttendanceLocation(location, geofence, 100000), "outside");
});
