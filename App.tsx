/**
 * TimePay Root Application Component
 * Central state management and conditional rendering based on authentication
 */

import React, { useState, useCallback } from "react";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import { UserSession } from "./src/types";
import { setAuthToken } from "./src/services/api";
import LoginScreen from "./src/screens/LoginScreen";
import DashboardScreen from "./src/screens/DashboardScreen";

interface AppState {
  isAuthenticated: boolean;
  userSessionData: UserSession | null;
  loading: boolean;
}

export default function App() {
  const [appState, setAppState] = useState<AppState>({
    isAuthenticated: false,
    userSessionData: null,
    loading: false,
  });

  /**
   * Handle successful login
   * Updates app state with user session data and sets API token
   */
  const handleLoginSuccess = useCallback((userSession: UserSession) => {
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
  const handleLogout = useCallback(() => {
    // Clear the authentication token
    setAuthToken(null);

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
      {isAuthenticated ? (
        <DashboardScreen
          userSessionData={userSessionData}
          onLogout={handleLogout}
        />
      ) : (
        <LoginScreen onLoginSuccess={handleLoginSuccess} />
      )}
    </GestureHandlerRootView>
  );
}
