import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";
import { AttendancePunchType } from "../types";
import { loadUserSession } from "./authStorage";
import * as Crypto from "expo-crypto";

export interface AttendanceEventMetadata {
  eventId: string;
  capturedAt: string;
}

const STORAGE_PREFIX = "timepay.attendance.event.v1.";
const pendingWrites = new Map<string, Promise<void>>();
const webStorage = new Map<string, string>();
export const createAttendanceEventId = (): string => Crypto.randomUUID();

const storage = {
  get: (key: string) => Platform.OS === "web"
    ? Promise.resolve(webStorage.get(key) ?? null)
    : SecureStore.getItemAsync(key),
  set: (key: string, value: string) => {
    if (Platform.OS === "web") {
      webStorage.set(key, value);
      return Promise.resolve();
    }
    return SecureStore.setItemAsync(key, value);
  },
  remove: (key: string) => {
    if (Platform.OS === "web") {
      webStorage.delete(key);
      return Promise.resolve();
    }
    return SecureStore.deleteItemAsync(key);
  },
};

const keyFor = (userId: number, companyId: number, type: AttendancePunchType) =>
  `${STORAGE_PREFIX}${companyId}.${userId}.${type}`;

const withKeyLock = async <T>(key: string, operation: () => Promise<T>): Promise<T> => {
  const previous = pendingWrites.get(key) ?? Promise.resolve();
  let release!: () => void;
  const current = new Promise<void>((resolve) => { release = resolve; });
  const tail = previous.then(() => current);
  pendingWrites.set(key, tail);
  await previous;
  try {
    return await operation();
  } finally {
    release();
    if (pendingWrites.get(key) === tail) pendingWrites.delete(key);
  }
};

const parseMetadata = (serialized: string | null): AttendanceEventMetadata | null => {
  if (!serialized) return null;
  try {
    const value = JSON.parse(serialized) as Partial<AttendanceEventMetadata>;
    if (typeof value.eventId !== "string" ||
        !/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value.eventId) ||
        typeof value.capturedAt !== "string" || Number.isNaN(Date.parse(value.capturedAt))) {
      return null;
    }
    return { eventId: value.eventId, capturedAt: value.capturedAt };
  } catch {
    return null;
  }
};

const currentIdentity = async () => {
  const session = await loadUserSession();
  if (!session || !Number.isSafeInteger(session.user.id) || !Number.isSafeInteger(session.company.id)) {
    throw new Error("Sign in again before submitting attendance.");
  }
  return { userId: session.user.id, companyId: session.company.id };
};

/** Return the durable id for this user's unfinished event, creating it once. */
export const getOrCreateAttendanceEvent = async (
  type: AttendancePunchType,
  capturedAt?: string
): Promise<AttendanceEventMetadata> => {
  const { userId, companyId } = await currentIdentity();
  const key = keyFor(userId, companyId, type);

  return withKeyLock(key, async () => {
    const existing = parseMetadata(await storage.get(key));
    if (existing) return existing;

    const metadata = {
      eventId: createAttendanceEventId(),
      capturedAt: capturedAt && !Number.isNaN(Date.parse(capturedAt))
        ? capturedAt
        : new Date().toISOString(),
    };
    await storage.set(key, JSON.stringify(metadata));
    return metadata;
  });
};

/** Clear only the matching completed event; a newer event must remain intact. */
export const completeAttendanceEvent = async (
  type: AttendancePunchType,
  eventId: string
): Promise<void> => {
  const { userId, companyId } = await currentIdentity();
  const key = keyFor(userId, companyId, type);

  await withKeyLock(key, async () => {
    const existing = parseMetadata(await storage.get(key));
    if (existing?.eventId === eventId) await storage.remove(key);
  });
};
