# TimePay API Architecture: Testing & Verification Matrix

This document records the formal backend API integration testing performed to validate the multi-tenant authentication scopes and Haversine coordinate geofencing thresholds.

## Environment Profiles
* **Backend API Base URL:** `http://127.0.0.1:8000/api`
* **Local Web Server Engine:** Laravel Artisan (`php artisan serve`)
* **Database Driver Engine:** MySQL 8.x
* **Testing Tool Protocol:** Postman / VS Code Thunder Client

---

## Seeding Environment Parameters
The following credentials and coordinates must be populated via database seed structures to execute this testing matrix:
* **Company Location Profile:** `SNSU Main Campus` (Lat: `9.791500`, Long: `125.491600`, Allowed Radius: `100 Meters`)
* **Test User Core Account:** `john@timepay.com` / Password: `password123`

---

## Test Suite Execution Log

### Test Case 1: Employee Authentication & Token Issuance
* **Endpoint Route:** `POST /auth/login`
* **Request Headers:**
  * `Accept`: `application/json`
  * `Content-Type`: `application/json`
* **JSON Payload Raw Body:**
```json
{
  "email": "john@timepay.com",
  "password": "password123"
}