# 🕒 TimePay - Employee Mobile App

A React Native (Expo) mobile application serving as the self-service employee portal for the **TimePay** SaaS platform. This app empowers employees to log attendance securely using **Geofencing** and **Cloud Face ID verification**, manage leave requests, and view their payroll data.

## 📱 Tech Stack
* **Framework:** React Native with **Expo**
* **Navigation:** React Navigation (Stack & Bottom Tabs)
* **Backend Integration:** Laravel (via REST API)

---

## ✨ Features
* **Secure Clock-In/Out:** Location-based geofence tracking and biometric face verification.
* **Dashboard Hub:** Real-time shift tracking and geofence status indicators.
* **Attendance History:** Monthly views of time logs, overtime, and break durations.
* **Leave Management:** Leave balance tracking and digital request forms.
* **Payslips:** View and download past earnings and PDF payslips.
* **Biometric Self-Service:** Request baseline photo resets directly from the profile screen.

---

## 🚀 Getting Started

Follow these instructions to set up the project and run it on your local development machine using Expo Go.

### 1. Prerequisites
Ensure you have the following installed:
* **Node.js** (v18 or higher recommended)
* **npm** or **yarn**
* **Expo Go App:** Download the "Expo Go" app on your physical iOS or Android device from the App Store or Google Play Store.

### 2. Installation
Clone the repository and install the JavaScript dependencies:

```bash
# Clone the repository
git clone https://github.com/your-organization/timepay-mobile.git

# Navigate to the directory
cd timepay-mobile

# Install dependencies
npm install
```

### 3. Environment Configuration
Create a `.env` file in the root of your project to connect to your Laravel backend. 

```bash
cp .env.example .env
```
Open the `.env` file and update the API base URL. *Note: If you are testing on a physical device, do not use `localhost`. Use your computer's local IP address (e.g., `192.168.1.x`).*
```env
EXPO_PUBLIC_API_BASE_URL=http://192.168.1.50:8000/api
```
src --> service --> api.ts

# running laravel
php artisan serve --host=0.0.0.0 --port=8000   
---

## 🏃‍♂️ Running the App

Start the Expo development server:

```bash
npx expo start
```

**To view the app:**
1. Open the **Expo Go** app on your physical smartphone.
2. **On Android:** Scan the QR code displayed in your terminal.
3. **On iOS:** Open your iPhone's standard Camera app, scan the QR code, and tap the notification to open it in Expo Go.

*(Optional)* If you prefer to use virtual emulators instead of a physical phone, you can press `a` in the terminal to launch Android Studio, or `i` to launch the iOS Simulator (Mac only).

---

## 🛠️ Folder Structure 

```text
timepay-mobile/
├── src/
│   ├── components/       # Reusable UI components (Buttons, Cards, Modals)
│   ├── navigation/       # React Navigation setup (Stack, Tabs)
│   ├── screens/          # Main app screens
│   │   ├── Home/         # Dashboard & Clock-in widget
│   │   ├── Attendance/   # History & Logs
│   │   ├── Leaves/       # Balances & Request forms
│   │   ├── Payslips/     # Earnings history
│   │   └── Profile/      # User settings & Biometric management
│   ├── services/         # API calls and Axios setup
│   └── utils/            # Helper functions (Date formatting, Geofence math)
├── App.js                # Application entry point
├── app.json              # Expo configuration file
└── package.json
```