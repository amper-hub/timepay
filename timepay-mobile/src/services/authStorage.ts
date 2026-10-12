import { Platform } from "react-native";
import * as SecureStore from "expo-secure-store";
import { UserSession } from "../types";

const SESSION_STORAGE_KEY = "timepay.auth.session.v1";
let webSession: string | null = null;

const parseSession = (value: string | null): UserSession | null => {
  if (!value) return null;

  try {
    const session = JSON.parse(value) as Partial<UserSession>;
    if (
      typeof session.token === "string" &&
      session.token.trim().length > 0 &&
      session.user &&
      typeof session.user.id === "number" &&
      session.company &&
      typeof session.company.id === "number"
    ) {
      return session as UserSession;
    }
  } catch {
    // An invalid or obsolete local session is treated as signed out.
  }

  return null;
};

export const saveUserSession = async (session: UserSession): Promise<void> => {
  const serialized = JSON.stringify(session);

  if (Platform.OS === "web") {
    // The production app targets native iOS and Android. Web sessions remain
    // in memory because browsers do not provide SecureStore's native guarantees.
    webSession = serialized;
    return;
  }

  await SecureStore.setItemAsync(SESSION_STORAGE_KEY, serialized);
};

export const loadUserSession = async (): Promise<UserSession | null> => {
  const serialized =
    Platform.OS === "web"
      ? webSession
      : await SecureStore.getItemAsync(SESSION_STORAGE_KEY);
  const session = parseSession(serialized);

  if (!session && serialized) {
    await clearUserSession();
  }

  return session;
};

export const clearUserSession = async (): Promise<void> => {
  if (Platform.OS === "web") {
    webSession = null;
    return;
  }

  await SecureStore.deleteItemAsync(SESSION_STORAGE_KEY);
};
