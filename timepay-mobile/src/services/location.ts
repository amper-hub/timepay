import * as Location from "expo-location";

export const HIGH_ACCURACY_LOCATION_TIMEOUT_MS = 10000;

export const LOCATION_FALLBACK_WARNING =
  "GPS signal is weak, so TimePay used your last known location. Your location might be slightly inaccurate.";

type AttendanceLocationResult = {
  location: Location.LocationObject;
  usedLastKnownLocation: boolean;
};

class LocationTimeoutError extends Error {
  constructor(timeoutMs: number) {
    super(`Unable to get high-accuracy GPS within ${timeoutMs}ms.`);
    this.name = "LocationTimeoutError";
  }
}

const withTimeout = async <T,>(
  promise: Promise<T>,
  timeoutMs: number
): Promise<T> => {
  let timeoutId: ReturnType<typeof setTimeout> | undefined;

  const timeoutPromise = new Promise<never>((_, reject) => {
    timeoutId = setTimeout(() => {
      reject(new LocationTimeoutError(timeoutMs));
    }, timeoutMs);
  });

  try {
    return await Promise.race([promise, timeoutPromise]);
  } finally {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }
  }
};

export const getHighAccuracyAttendanceLocation = async (
  timeoutMs = HIGH_ACCURACY_LOCATION_TIMEOUT_MS
): Promise<AttendanceLocationResult> => {
  try {
    const location = await withTimeout(
      Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Highest,
      }),
      timeoutMs
    );

    return {
      location,
      usedLastKnownLocation: false,
    };
  } catch (error) {
    if (!(error instanceof LocationTimeoutError)) {
      throw error;
    }

    console.warn(
      "[Location] High-accuracy GPS request timed out. Falling back to last known position.",
      error
    );

    const lastKnownLocation = await Location.getLastKnownPositionAsync();

    if (!lastKnownLocation) {
      throw error;
    }

    return {
      location: lastKnownLocation,
      usedLastKnownLocation: true,
    };
  }
};
