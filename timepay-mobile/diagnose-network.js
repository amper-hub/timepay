#!/usr/bin/env node

/**
 * TimePay Network Diagnostics Script
 * Checks connectivity to the configured Laravel API URL
 * 
 * Usage:
 *   node diagnose-network.js
 *   or
 *   npx ts-node diagnose-network.ts
 */

const http = require("http");
const https = require("https");
const os = require("os");

const configuredUrl = process.env.EXPO_PUBLIC_API_BASE_URL;
if (!configuredUrl) {
  console.error("Set EXPO_PUBLIC_API_BASE_URL before running this diagnostic.");
  process.exit(1);
}

let apiUrl;
try {
  apiUrl = new URL(configuredUrl);
  if (!['http:', 'https:'].includes(apiUrl.protocol) || apiUrl.username || apiUrl.password) {
    throw new Error("invalid URL");
  }
} catch {
  console.error("EXPO_PUBLIC_API_BASE_URL must be an absolute HTTP or HTTPS URL.");
  process.exit(1);
}

apiUrl.pathname = apiUrl.pathname.replace(/(?:\/api)+\/?$/i, "").replace(/\/+$/, "") + "/api";
const TARGET_URL = apiUrl.toString().replace(/\/$/, "");
const TARGET_HOST = apiUrl.hostname;
const TARGET_PORT = Number(apiUrl.port || (apiUrl.protocol === "https:" ? 443 : 80));
const requestModule = apiUrl.protocol === "https:" ? https : http;
const TIMEOUT_MS = 5000;

// Color codes for terminal output
const colors = {
  reset: "\x1b[0m",
  bright: "\x1b[1m",
  green: "\x1b[32m",
  red: "\x1b[31m",
  yellow: "\x1b[33m",
  blue: "\x1b[34m",
  cyan: "\x1b[36m",
};

function log(color, ...args) {
  console.log(`${colors[color]}${args.join(" ")}${colors.reset}`);
}

function logSection(title) {
  log("cyan", "\n" + "=".repeat(60));
  log("bright", `  ${title}`);
  log("cyan", "=".repeat(60) + "\n");
}

async function getLocalIPs() {
  const interfaces = os.networkInterfaces();
  const ips = [];
  
  for (const [name, addrs] of Object.entries(interfaces)) {
    for (const addr of addrs) {
      if (addr.family === "IPv4" && !addr.internal) {
        ips.push({ interface: name, ip: addr.address });
      }
    }
  }
  
  return ips;
}

function testBasicConnectivity() {
  return new Promise((resolve) => {
    const request = requestModule.get(
      TARGET_URL,
      { timeout: TIMEOUT_MS },
      (response) => {
        request.destroy();
        resolve({
          success: true,
          statusCode: response.statusCode,
          statusMessage: response.statusMessage,
        });
      }
    );

    request.on("error", (error) => {
      resolve({
        success: false,
        error: error.code,
        message: error.message,
      });
    });

    request.on("timeout", () => {
      request.destroy();
      resolve({
        success: false,
        error: "ECONNTIMEDOUT",
        message: `Request timed out after ${TIMEOUT_MS}ms`,
      });
    });
  });
}

function testDNSResolution() {
  return new Promise((resolve) => {
    const dns = require("dns");
    
    // Test both IP and hostname resolution
    dns.lookup(TARGET_HOST, (err, address) => {
      if (err) {
        resolve({
          success: false,
          error: err.code,
          message: `Failed to resolve ${TARGET_HOST}: ${err.message}`,
        });
      } else {
        resolve({
          success: true,
          resolved: address,
          message: `Successfully resolved ${TARGET_HOST} to ${address}`,
        });
      }
    });
  });
}

function testPortConnectivity() {
  return new Promise((resolve) => {
    const net = require("net");
    const socket = new net.Socket();

    socket.setTimeout(TIMEOUT_MS);

    socket.on("connect", () => {
      socket.destroy();
      resolve({
        success: true,
        message: `Port ${TARGET_PORT} is open and accepting connections`,
      });
    });

    socket.on("timeout", () => {
      socket.destroy();
      resolve({
        success: false,
        error: "TIMEOUT",
        message: `Connection attempt to port ${TARGET_PORT} timed out`,
      });
    });

    socket.on("error", (error) => {
      resolve({
        success: false,
        error: error.code,
        message: `Failed to connect to port ${TARGET_PORT}: ${error.message}`,
      });
    });

    socket.connect(TARGET_PORT, TARGET_HOST);
  });
}

function testAPIEndpoint() {
  return new Promise((resolve) => {
    const request = requestModule.get(
      `${TARGET_URL}/user`,
      {
        timeout: TIMEOUT_MS,
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
      },
      (response) => {
        let data = "";

        response.on("data", (chunk) => {
          data += chunk;
        });

        response.on("end", () => {
          request.destroy();
          resolve({
            success: response.statusCode < 500,
            statusCode: response.statusCode,
            statusMessage: response.statusMessage,
            headers: response.headers,
            body: data.substring(0, 200), // First 200 chars
            message:
              response.statusCode === 401
                ? "API responded (401 expected without token)"
                : `API responded with status ${response.statusCode}`,
          });
        });
      }
    );

    request.on("error", (error) => {
      resolve({
        success: false,
        error: error.code,
        message: `Failed to reach API endpoint: ${error.message}`,
      });
    });

    request.on("timeout", () => {
      request.destroy();
      resolve({
        success: false,
        error: "ECONNTIMEDOUT",
        message: `API endpoint request timed out after ${TIMEOUT_MS}ms`,
      });
    });
  });
}

function displayResult(title, result, indent = 0) {
  const prefix = " ".repeat(indent);
  const statusColor = result.success ? "green" : "red";
  const statusText = result.success ? "✓ PASS" : "✗ FAIL";

  console.log(
    `${prefix}${colors[statusColor]}${statusText}${colors.reset} - ${title}`
  );

  if (result.message) {
    console.log(`${prefix}  ${result.message}`);
  }

  if (result.error) {
    console.log(`${prefix}  Error Code: ${colors.red}${result.error}${colors.reset}`);
  }

  if (result.statusCode) {
    console.log(`${prefix}  HTTP Status: ${result.statusCode} ${result.statusMessage}`);
  }
}

async function runDiagnostics() {
  logSection("🔍 TimePay Network Diagnostics");

  // Section 1: Local Machine Information
  logSection("1. Local Machine Information");

  const localIPs = await getLocalIPs();
  log("bright", "Local IPv4 Addresses:");
  localIPs.forEach((item) => {
    console.log(
      `  ${colors.cyan}${item.interface}${colors.reset}: ${colors.green}${item.ip}${colors.reset}`
    );
  });

  const targetIPFound = localIPs.some((item) => item.ip === TARGET_HOST);
  if (targetIPFound) {
    log("green", "✓ Target IP matches local machine!");
  } else {
    log("yellow", "⚠ Target IP does NOT match any local interface");
    log("yellow", `  Make sure ${TARGET_HOST} is reachable from this device`);
  }

  // Section 2: DNS Resolution
  logSection("2. DNS Resolution Test");

  const dnsResult = await testDNSResolution();
  displayResult("DNS Lookup", dnsResult, 2);

  // Section 3: Port Connectivity
  logSection("3. Port Connectivity Test");

  log("bright", `Testing port ${TARGET_PORT} on ${TARGET_HOST}...`);
  const portResult = await testPortConnectivity();
  displayResult("TCP Port Connection", portResult, 2);

  // Section 4: Basic HTTP Connectivity
  logSection("4. Basic HTTP Connectivity");

  log("bright", `Testing connection to ${TARGET_URL}...`);
  const httpResult = await testBasicConnectivity();
  displayResult("HTTP Connection", httpResult, 2);

  // Section 5: API Endpoint Test
  logSection("5. API Endpoint Test");

  log("bright", "Testing Laravel API endpoint...");
  const apiResult = await testAPIEndpoint();
  displayResult("API Endpoint", apiResult, 2);

  if (apiResult.statusCode) {
    console.log(`  ${colors.cyan}Status Code:${colors.reset} ${apiResult.statusCode}`);
  }

  // Section 6: Diagnosis Summary
  logSection("📋 Diagnosis Summary");

  const allPass = [dnsResult, portResult, httpResult, apiResult].every(
    (r) => r.success
  );

  if (allPass) {
    log(
      "green",
      "✓ All tests passed! Your network connection appears healthy."
    );
    log("green", "If the app still fails, the issue may be:");
    console.log("  • Expo/React Native specific configuration");
    console.log("  • CORS headers mismatch");
    console.log("  • Android/iOS permission issues");
  } else {
    log("red", "✗ Some tests failed. Common issues:");

    if (!dnsResult.success) {
      console.log("  • DNS Resolution: Check your network/IP configuration");
    }

    if (!portResult.success) {
      console.log(
        "  • Port 8000: Is Laravel server running? Windows Firewall blocking?"
      );
      console.log(
        "    Fix: Run Laravel with: php artisan serve --host=0.0.0.0 --port=8000"
      );
    }

    if (!httpResult.success) {
      console.log("  • HTTP Connection: Network or server issue");
      console.log("    Check: Firewall, server logs, network permissions");
    }

    if (!apiResult.success) {
      console.log("  • API Endpoint: Laravel not responding on expected endpoint");
      console.log("    Check: Laravel routes, API configuration, CORS settings");
    }
  }

  // Section 7: Quick Fixes
  logSection("🔧 Quick Fixes to Try");

  console.log(`${colors.yellow}1. Verify Laravel is running:${colors.reset}`);
  console.log(`   cd timepay-backend`);
  console.log(
    `   php artisan serve --host=0.0.0.0 --port=8000\n`
  );

  console.log(`${colors.yellow}2. Verify network connection:${colors.reset}`);
  console.log(`   Windows: ipconfig | findstr IPv4`);
  console.log(`   Mac/Linux: ifconfig | grep inet\n`);

  console.log(`${colors.yellow}3. Test from command line:${colors.reset}`);
  console.log(`   curl -X GET ${TARGET_URL}\n`);

  console.log(`${colors.yellow}4. Check Windows Firewall:${colors.reset}`);
  console.log(`   Allow port 8000 in Windows Firewall\n`);

  console.log(`${colors.yellow}5. Set EXPO_PUBLIC_API_BASE_URL in .env.local and restart Expo.${colors.reset}`);

  // Section 8: Network Environment
  logSection("🌐 Network Environment");

  console.log(`${colors.cyan}Target Server:${colors.reset} ${colors.bright}${TARGET_URL}${colors.reset}`);
  console.log(`${colors.cyan}Timeout:${colors.reset} ${TIMEOUT_MS}ms`);
  console.log(
    `${colors.cyan}Platform:${colors.reset} ${os.platform()} ${os.arch()}`
  );
  console.log(
    `${colors.cyan}Node Version:${colors.reset} ${process.version}\n`
  );

  log("cyan", "=".repeat(60));
  log("bright", "Diagnostics Complete");
  log("cyan", "=".repeat(60) + "\n");
}

// Run diagnostics
runDiagnostics().catch(console.error);
