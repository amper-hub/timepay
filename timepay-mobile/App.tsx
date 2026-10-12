/**
 * TimePay Root Application Component
 * Central state management and conditional rendering based on authentication
 */

import React, { useState, useCallback, useEffect } from "react";
import { ActivityIndicator, Alert, View } from "react-native";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { UserSession } from "./src/types";
import { apiService, setAuthToken } from "./src/services/api";
import {
  clearUserSession,
  loadUserSession,
  saveUserSession,
} from "./src/services/authStorage";
import LoginScreen from "./src/screens/LoginScreen";
import AppNavigator from "./src/navigation/AppNavigator";

interface AppState {
  isAuthenticated: boolean;
  userSessionData: UserSession | null;
  loading: boolean;
}

export default function App() {
  const [appState, setAppState] = useState<AppState>({
    isAuthenticated: false,
    userSessionData: null,
    loading: true,
  });

  useEffect(() => {
    let mounted = true;

    loadUserSession()
      .then((session) => {
        if (!mounted) return;

        if (session) {
          setAuthToken(session.token);
          setAppState({
            isAuthenticated: true,
            userSessionData: session,
            loading: false,
          });
          return;
        }

        setAppState({
          isAuthenticated: false,
          userSessionData: null,
          loading: false,
        });
      })
      .catch(() => {
        if (!mounted) return;
        setAuthToken(null);
        setAppState({
          isAuthenticated: false,
          userSessionData: null,
          loading: false,
        });
      });

    return () => {
      mounted = false;
    };
  }, []);

  /**
   * Handle successful login
   * Updates app state with user session data and sets API token
   */
  const handleLoginSuccess = useCallback(async (userSession: UserSession) => {
    await saveUserSession(userSession);
    // Set the authentication token for API requests
    setAuthToken(userSession.token);

    // Update app state
    setAppState({
      isAuthenticated: true,
      userSessionData: userSession,
      loading: false,
    });
  }, []);

  /**
   * Handle user logout
   * Clears session data and resets app to login state
   */
  const handleLogout = useCallback(async () => {
    try {
      await apiService.logout();
    } catch {
      // Clear local credentials even if the network is unavailable.
    }

    // Clear the authentication token
    setAuthToken(null);
    try {
      await clearUserSession();
    } catch {
      Alert.alert(
        "Sign-out needs attention",
        "TimePay signed you out on this device, but could not clear its saved session. Please reopen the app while online and sign out again."
      );
    }

    // Reset app state
    setAppState({
      isAuthenticated: false,
      userSessionData: null,
      loading: false,
    });
  }, []);

  const { isAuthenticated, userSessionData } = appState;

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      {appState.loading ? (
        <View style={{ flex: 1, alignItems: "center", justifyContent: "center" }}>
          <ActivityIndicator color="#059669" size="large" />
        </View>
      ) : isAuthenticated ? (
        <AppNavigator
          userSessionData={userSessionData}
          onLogout={handleLogout}
        />
      ) : (
        <LoginScreen onLoginSuccess={handleLoginSuccess} />
      )}
    </GestureHandlerRootView>
  );
}
