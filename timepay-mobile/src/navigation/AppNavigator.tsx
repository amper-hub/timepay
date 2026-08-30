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
import ProfileScreen from "../screens/Profile";
import { colors } from "../theme/colors";

export type EmployeeTabParamList = {
  Home: undefined;
  Attendance: undefined;
  FaceVerification: { action: "clock_in" | "clock_out" };
  Leave: undefined;
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
      minWidth: 50,
      height: 38,
      borderRadius: 18,
      alignItems: "center",
      justifyContent: "center",
      backgroundColor: focused ? "#E6F4EA" : "transparent",
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
          tabBarShowLabel: false,
          tabBarActiveTintColor: colors.primary,
          tabBarInactiveTintColor: "#475569",
          tabBarLabelStyle: {
            fontSize: 12,
            fontWeight: "700",
          },
          tabBarStyle: {
            height: 64,
            paddingTop: 10,
            paddingBottom: 10,
            borderTopColor: colors.emeraldSoft,
            backgroundColor: colors.white,
          },
        }}
      >
        <Tab.Screen
          name="Home"
          options={{
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
          }}
        >
          {(props) => <FaceVerificationScreen {...props} />}
        </Tab.Screen>

        <Tab.Screen
          name="Leave"
          options={{
            tabBarIcon: ({ focused }) =>
              tabIcon("document-text", "document-text-outline", focused),
          }}
        >
          {() => <LeaveScreen userSessionData={userSessionData} />}
        </Tab.Screen>

        <Tab.Screen
          name="Profile"
          options={{
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
