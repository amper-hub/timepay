import React from "react";
import { Image, ImageStyle, StyleProp } from "react-native";

interface TimePayLogoProps {
  style?: StyleProp<ImageStyle>;
}

const logoSource = require("../../resources/img/timepay-logo.png");

const TimePayLogo: React.FC<TimePayLogoProps> = ({ style }) => (
  <Image
    source={logoSource}
    resizeMode="contain"
    style={[{ width: 138, height: 44 }, style]}
    accessibilityLabel="TimePay"
  />
);

export default TimePayLogo;
