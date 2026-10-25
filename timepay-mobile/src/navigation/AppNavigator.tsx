import React from "react";
import { View } from "react-native";
import { NavigationContainer } from "@react-navigation/native";
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";
import { Ionicons } from "@expo/vector-icons";
import { UserSession } from "../types";
import HomeScreen from "../screens/HomeScreen";
import AttendanceScreen from "../screens/Attendance";
import FaceVerificationScreen from "../screens/FaceVerification";
import LeaveScreen from "../screens/Leaves";
import PayrollScreen from "../screens/PayrollScreen";
import ProfileScreen from "../screens/Profile";
import { colors } from "../theme/colors";

export type EmployeeTabParamList = {
  Home: undefined;
  Attendance: undefined;
  FaceVerification: { action: "clock_in" | "clock_out" };
  Leave: undefined;
  Payroll: undefined;
  Profile: undefined;
};

interface AppNavigatorProps {
  userSessionData: UserSession | null;
  onLogout: () => void;
}

const Tab = createBottomTabNavigator<EmployeeTabParamList>();

const tabIcon = (
  activeIcon: keyof typeof Ionicons.glyphMap,
  inactiveIcon: keyof typeof Ionicons.glyphMap,
  focused: boolean
) => (
  <View
    style={{
      minWidth: 42,
      height: 30,
      borderRadius: 15,
      alignItems: "center",
      justifyContent: "center",
      backgroundColor: focused ? colors.emeraldSurface : "transparent",
    }}
  >
    <Ionicons
      name={focused ? activeIcon : inactiveIcon}
      size={24}
      color={focused ? colors.primary : "#475569"}
    />
  </View>
);

const AppNavigator: React.FC<AppNavigatorProps> = ({
  userSessionData,
  onLogout,
}) => {
  return (
    <NavigationContainer>
      <Tab.Navigator
        initialRouteName="Home"
        screenOptions={{
          headerShown: false,
          tabBarShowLabel: true,
          tabBarActiveTintColor: colors.primary,
          tabBarInactiveTintColor: colors.muted,
          tabBarLabelStyle: {
            fontSize: 11,
            fontWeight: "600",
            marginTop: 2,
            marginBottom: 1,
          },
          tabBarItemStyle: {
            flex: 1,
          },
          tabBarStyle: {
            flexDirection: "row",
            justifyContent: "space-around",
            minHeight: 64,
            paddingTop: 6,
            paddingBottom: 4,
            borderTopColor: colors.border,
            backgroundColor: colors.white,
            elevation: 0,
          },
        }}
      >
        <Tab.Screen
          name="Home"
          options={{
            tabBarAccessibilityLabel: "Home",
            tabBarIcon: ({ focused }) => tabIcon("home", "home-outline", focused),
          }}
        >
          {(props) => (
            <HomeScreen {...props} userSessionData={userSessionData} />
          )}
        </Tab.Screen>

        <Tab.Screen
          name="Attendance"
          options={{
            tabBarAccessibilityLabel: "Attendance",
            tabBarIcon: ({ focused }) =>
              tabIcon("calendar", "calendar-outline", focused),
          }}
        >
          {(props) => (
            <AttendanceScreen
              {...props}
              userSessionData={userSessionData}
              onLogout={onLogout}
            />
          )}
        </Tab.Screen>

        <Tab.Screen
          name="FaceVerification"
          options={{
            tabBarButton: () => null,
            tabBarItemStyle: { display: "none" },
          }}
        >
          {(props) => <FaceVerificationScreen {...props} />}
        </Tab.Screen>

        <Tab.Screen
          name="Leave"
          options={{
            tabBarAccessibilityLabel: "Leave",
            tabBarIcon: ({ focused }) =>
              tabIcon("document-text", "document-text-outline", focused),
          }}
        >
          {() => <LeaveScreen userSessionData={userSessionData} />}
        </Tab.Screen>

        <Tab.Screen
          name="Payroll"
          options={{
            tabBarAccessibilityLabel: "Payroll",
            tabBarIcon: ({ focused }) => tabIcon("wallet", "wallet-outline", focused),
          }}
        >
          {(props) => <PayrollScreen {...props} />}
        </Tab.Screen>

        <Tab.Screen
          name="Profile"
          options={{
            tabBarAccessibilityLabel: "Profile",
            tabBarIcon: ({ focused }) =>
              tabIcon("person", "person-outline", focused),
          }}
        >
          {() => (
            <ProfileScreen
              userSessionData={userSessionData}
              onLogout={onLogout}
            />
          )}
        </Tab.Screen>
      </Tab.Navigator>
    </NavigationContainer>
  );
};

export default AppNavigator;
