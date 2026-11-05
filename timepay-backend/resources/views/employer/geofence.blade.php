@extends('layouts.employer')

@section('title', 'Geofence Settings - TimePay Employer Portal')
@section('header_title', 'Geofence Settings')

@section('content')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <div class="space-y-6">
        <header class="border-b border-slate-200 pb-5">
            <p class="text-sm font-semibold text-emerald-700">Business</p>
            <h2 class="employer-page-heading">Attendance and location rules</h2>
            <p class="employer-page-description">Choose the worksite boundary used by existing attendance verification.</p>
        </header>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-base text-red-900" role="alert">
                <p class="font-semibold">Review the location settings.</p>
                <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
        <section class="employer-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h3 class="employer-section-heading">Worksite boundary</h3>
                    <p class="mt-1 text-sm text-slate-600">Move the pin, select the map, or use your current location.</p>
                </div>
                <button type="button" id="locate-btn" class="employer-secondary-button">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-600"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2z"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>
                    Find My Location
                </button>
            </div>

            <form method="POST" action="{{ route('employer.geofence.update') }}" class="space-y-5 p-4 sm:p-6">
                @csrf

                <div id="geofence-map" role="application" aria-label="Map for setting the worksite location" class="z-10 h-72 w-full rounded-xl border border-slate-300 sm:h-[350px]"></div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="latitude" class="employer-field-label">Latitude</label>
                        <input
                            id="latitude"
                            name="latitude"
                            type="number"
                            step="0.00000001"
                            readonly
                            value="{{ old('latitude', $company->latitude ?? '') }}"
                            placeholder="e.g. 7.0702000"
                            class="employer-field-control cursor-not-allowed bg-slate-100"
                        >
                        @error('latitude')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="longitude" class="employer-field-label">Longitude</label>
                        <input
                            id="longitude"
                            name="longitude"
                            type="number"
                            step="0.00000001"
                            readonly
                            value="{{ old('longitude', $company->longitude ?? '') }}"
                            placeholder="e.g. 125.6171000"
                            class="employer-field-control cursor-not-allowed bg-slate-100"
                        >
                        @error('longitude')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="geofence_radius_meters" class="employer-field-label">Allowed radius</label>
                    <div class="mt-2 flex max-w-sm items-center rounded-lg border border-slate-300 bg-white shadow-sm focus-within:border-emerald-600 focus-within:ring-2 focus-within:ring-emerald-600/15">
                        <input
                            id="geofence_radius_meters"
                            name="geofence_radius_meters"
                            type="number"
                            min="10"
                            max="10000"
                            value="{{ old('geofence_radius_meters', $company->geofence_radius_meters ?? 100) }}"
                            class="w-full rounded-l-lg border-0 px-3 py-2.5 text-base text-slate-900 outline-none focus:ring-2 focus:ring-inset focus:ring-emerald-600"
                            required
                        >
                        <span class="border-l border-slate-200 px-3 text-sm font-medium text-slate-600">meters</span>
                    </div>
                    @error('geofence_radius_meters')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ([50, 100, 200, 500] as $radius)
                        <button type="button" data-radius="{{ $radius }}" class="radius-btn min-h-10 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600">
                            {{ $radius }}m
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                    <a href="{{ route('employer.settings.index') }}" class="employer-secondary-button">Business settings</a>
                    <button type="submit" class="employer-primary-button">
                        Save location rules
                    </button>
                </div>
            </form>
        </section>

        <aside class="employer-card self-start p-5 sm:p-6">
            <h3 class="employer-section-heading">Saved boundary</h3>
            <dl class="mt-5 space-y-4">
                <div>
                    <dt class="text-sm text-slate-600">Latitude</dt>
                    <dd class="mt-1 break-all font-mono text-base font-semibold text-slate-950">{{ $company->latitude ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-600">Longitude</dt>
                    <dd class="mt-1 break-all font-mono text-base font-semibold text-slate-950">{{ $company->longitude ?? 'Not set' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-600">Radius</dt>
                    <dd class="mt-1 text-base font-semibold text-slate-950">{{ number_format($company->geofence_radius_meters ?? 100) }} meters</dd>
                </div>
            </dl>
        </aside>
        </div>
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
                color: '#059669',
                fillColor: '#10b981',
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
