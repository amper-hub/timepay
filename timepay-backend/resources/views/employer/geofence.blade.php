@extends('layouts.employer')

@section('title', 'Geofence Settings - TimePay Employer Portal')
@section('header_title', 'Geofence Settings')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h2 class="text-base font-semibold text-slate-950">Workspace Boundary</h2>
                    <p class="mt-1 text-sm text-slate-500">Drag the pin, click the map, or use your GPS to set the workspace coordinates.</p>
                </div>
                <button type="button" id="locate-btn" class="flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-950/10">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2z"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                    Find My Location
                </button>
            </div>

            <form method="POST" action="{{ route('employer.geofence.update') }}" class="space-y-6 p-6">
                @csrf

                <div id="geofence-map" class="h-[350px] w-full rounded-lg border border-slate-300 z-10 shadow-sm"></div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="latitude" class="block text-sm font-medium text-slate-700">Latitude</label>
                        <input
                            id="latitude"
                            name="latitude"
                            type="number"
                            step="0.00000001"
                            readonly
                            value="{{ old('latitude', $company->latitude ?? '') }}"
                            placeholder="e.g. 7.0702000"
                            class="mt-2 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 cursor-not-allowed"
                        >
                        @error('latitude')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="longitude" class="block text-sm font-medium text-slate-700">Longitude</label>
                        <input
                            id="longitude"
                            name="longitude"
                            type="number"
                            step="0.00000001"
                            readonly
                            value="{{ old('longitude', $company->longitude ?? '') }}"
                            placeholder="e.g. 125.6171000"
                            class="mt-2 w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm shadow-sm outline-none transition focus:border-slate-950 focus:ring-2 focus:ring-slate-950/10 cursor-not-allowed"
                        >
                        @error('longitude')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="geofence_radius_meters" class="block text-sm font-medium text-slate-700">Geofence Radius</label>
                    <div class="mt-2 flex max-w-sm items-center rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-slate-950 focus-within:ring-2 focus-within:ring-slate-950/10">
                        <input
                            id="geofence_radius_meters"
                            name="geofence_radius_meters"
                            type="number"
                            min="10"
                            max="10000"
                            value="{{ old('geofence_radius_meters', $company->geofence_radius_meters ?? 100) }}"
                            class="w-full rounded-l-lg border-0 px-3 py-2 text-sm outline-none"
                            required
                        >
                        <span class="border-l border-slate-200 px-3 text-sm font-medium text-slate-500">meters</span>
                    </div>
                    @error('geofence_radius_meters')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ([50, 100, 200, 500] as $radius)
                        <button type="button" data-radius="{{ $radius }}" class="radius-btn rounded-full border border-slate-200 px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-950/10">
                            {{ $radius }}m
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-6">
                    <a href="{{ route('employer.dashboard') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Cancel</a>
                    <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                        Save Geofence
                    </button>
                </div>
            </form>
        </section>

        <aside class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm self-start">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Current Settings</h3>
            <dl class="mt-5 space-y-4">
                <div>
                    <dt class="text-sm text-slate-500">Latitude</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-slate-950">{{ $company->latitude ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Longitude</dt>
                    <dd class="mt-1 font-mono text-sm font-semibold text-slate-950">{{ $company->longitude ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Radius</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-950">{{ number_format($company->geofence_radius_meters ?? 100) }} meters</dd>
                </div>
            </dl>
        </aside>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const radiusInput = document.getElementById('geofence_radius_meters');
            const locateBtn = document.getElementById('locate-btn');
            
            // Default to Davao City coordinates if nothing is saved in DB yet
            let currentLat = parseFloat(latInput.value) || 7.0702;
            let currentLng = parseFloat(lngInput.value) || 125.6171;
            let currentRadius = parseFloat(radiusInput.value) || 100;

            // 1. Initialize Map
            const map = L.map('geofence-map').setView([currentLat, currentLng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);

            // 2. Add Draggable Marker
            const marker = L.marker([currentLat, currentLng], { draggable: true }).addTo(map);

            // 3. Add Radius Circle
            const circle = L.circle([currentLat, currentLng], {
                color: '#0f172a', // slate-950 to match your theme
                fillColor: '#0f172a',
                fillOpacity: 0.15,
                radius: currentRadius
            }).addTo(map);

            // Function to sync map UI with input fields
            function updateGeofence(lat, lng) {
                latInput.value = lat.toFixed(8);
                lngInput.value = lng.toFixed(8);
                marker.setLatLng([lat, lng]);
                circle.setLatLng([lat, lng]);
            }

            // Map Events
            marker.on('dragend', function(e) {
                const pos = marker.getLatLng();
                updateGeofence(pos.lat, pos.lng);
            });

            map.on('click', function(e) {
                updateGeofence(e.latlng.lat, e.latlng.lng);
            });

            // Radius Input Event (User types a number)
            radiusInput.addEventListener('input', function(e) {
                let newRadius = parseFloat(e.target.value);
                if (newRadius > 0) {
                    circle.setRadius(newRadius);
                }
            });

            // Radius Button Events (User clicks 50m, 100m, etc)
            document.querySelectorAll('.radius-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const newRadius = this.getAttribute('data-radius');
                    radiusInput.value = newRadius;
                    circle.setRadius(parseFloat(newRadius));
                });
            });

            // GPS Find My Location Event
            locateBtn.addEventListener('click', function() {
                locateBtn.innerHTML = 'Locating...';
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            updateGeofence(lat, lng);
                            map.setView([lat, lng], 17); // Zoom in on location
                            locateBtn.innerHTML = 'Location Found!';
                            setTimeout(() => locateBtn.innerHTML = 'Find My Location', 3000);
                        },
                        function(error) {
                            alert("Unable to retrieve your location. Please check browser permissions.");
                            locateBtn.innerHTML = 'Find My Location';
                        },
                        { enableHighAccuracy: true }
                    );
                } else {
                    alert("Geolocation is not supported by your browser.");
                }
            });
        });
    </script>
@endsection