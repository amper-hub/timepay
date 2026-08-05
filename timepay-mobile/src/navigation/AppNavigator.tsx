import React from "react";
import { Text } from "react-native";
import { NavigationContainer } from "@react-navigation/native";
import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";
import { UserSession } from "../types";
import HomeScreen from "../screens/HomeScreen";
import AttendanceScreen from "../screens/AttendanceScreen";
import LeaveScreen from "../screens/Leaves";
import ProfileScreen from "../screens/ProfileScreen";

export type EmployeeTabParamList = {
  Home: undefined;
  Attendance: undefined;
  Leave: undefined;
  Profile: undefined;
};

interface AppNavigatorProps {
  userSessionData: UserSession | null;
  onLogout: () => void;
}

const Tab = createBottomTabNavigator<EmployeeTabParamList>();

const tabIcon = (label: string, focused: boolean) => (
  <Text
    style={{
      color: focused ? "#4f46e5" : "#94a3b8",
      fontSize: 12,
      fontWeight: "900",
    }}
  >
    {label}
  </Text>
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
          tabBarActiveTintColor: "#4f46e5",
          tabBarInactiveTintColor: "#94a3b8",
          tabBarLabelStyle: {
            fontSize: 12,
            fontWeight: "700",
          },
          tabBarStyle: {
            height: 64,
            paddingTop: 8,
            paddingBottom: 8,
            borderTopColor: "#e2e8f0",
            backgroundColor: "#ffffff",
          },
        }}
      >
        <Tab.Screen
          name="Home"
          options={{
            tabBarIcon: ({ focused }) => tabIcon("HM", focused),
          }}
        >
          {(props) => (
            <HomeScreen {...props} userSessionData={userSessionData} />
          )}
        </Tab.Screen>

        <Tab.Screen
          name="Attendance"
          options={{
            tabBarIcon: ({ focused }) => tabIcon("AT", focused),
          }}
        >
          {() => (
            <AttendanceScreen
              userSessionData={userSessionData}
              onLogout={onLogout}
            />
          )}
        </Tab.Screen>

        <Tab.Screen
          name="Leave"
          options={{
            tabBarIcon: ({ focused }) => tabIcon("LV", focused),
          }}
        >
          {() => <LeaveScreen userSessionData={userSessionData} />}
        </Tab.Screen>

        <Tab.Screen
          name="Profile"
          options={{
            tabBarIcon: ({ focused }) => tabIcon("PR", focused),
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
