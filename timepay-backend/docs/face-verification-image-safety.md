# Face verification image safety

## Findings

`FacePlusPlusService::prepareFacePlusPlusImage()` previously read the complete upload, inspected dimensions from that in-memory copy, then decoded the original full-resolution image with GD before reducing its longest edge to 1600 pixels. That order allowed small compressed files with very large decoded dimensions to exhaust PHP memory. The API endpoint accepts JPEG and PNG; its current rules allow a 5 MiB selfie and three 2 MiB liveness stills.

The face-verification screen previously applied its 1600-pixel/0.85 JPEG compression only to the primary selfie. Liveness stills were kept from low-quality preview captures without the same explicit resize/re-encode step. The API client also supplied a bare multipart Content-Type, which can prevent the runtime from adding its required boundary.

Login is handled by a separate authentication endpoint and does not call the face image preparation service. The reported login failure cannot be attributed to this image path from the available code evidence; its incident logs and request details are needed to diagnose that independently.

## Processing policy

- Accept actual JPEG or PNG file signatures, regardless of client filename.
- Reject empty or greater-than-5 MiB files before reading their contents.
- Inspect dimensions from the file header before full file reads or GD decoding.
- Reject either edge above 8000 pixels or more than 16 million total pixels. The pixel check uses integer division to avoid multiplication overflow.
- Re-encode through GD to JPEG, longest edge at most 1600 pixels, quality 82. Existing 1600-pixel Face++ normalization is preserved.
- Without GD, retain the existing fail-closed fallback for files over 2 MiB or with edges above 1600 pixels.
- On mobile, apply a longest-edge 1600 pixel bound and JPEG quality 0.85 to the selfie and each liveness still. Preserve open, closed, reopened field names and order. Keep the compressed liveness copy until submission finishes; delete its source after successful preparation and clean retained files on existing completion/exit paths.

The 16 MP limit bounds typical decoded true-color storage to roughly 64 MiB per image before GD and PHP overhead; 1600-pixel target buffers are small by comparison. This is a conservative per-image budget under the observed 128 MiB CLI limit, but it is not a mathematical guarantee for every decoder build, simultaneous worker activity, or unrelated PHP allocations. Keep PHP memory limits and worker concurrency under operational monitoring.

## Request limits and deployment

The application accepts up to 5 MiB for one image and 2 MiB for each of three liveness images, so the theoretical image body may reach 11 MiB plus multipart and form fields. The inspected local PHP CLI reports `upload_max_filesize=2M`, `post_max_size=8M`, and `memory_limit=128M`; therefore this environment cannot accept the largest request allowed by Laravel validation. These are CLI values, not verified FPM/Apache or production values.

Before deployment, configure the serving PHP SAPI with `upload_max_filesize` of at least `5M` and `post_max_size` of at least `12M`, and verify the reverse proxy/web server body limit is also at least `12M`. Apply through the environment's reviewed PHP-FPM/Apache/container configuration and restart the relevant service; do not edit production configuration from this patch. Measure representative device request sizes and set lower limits only if product capture testing shows they are safe. Do not increase PHP memory as a substitute for image validation.

## Validation and remaining checks

Automated tests use synthetic images only. They cover malformed data, compressed-size rejection, pixel-limit rejection before GD decode, supported JPEG normalization, and existing Face++ success/failure behavior. Device-level file sizes, actual camera dimensions, Face++ quality, and image-manipulator temporary-file behavior have not been measured here.

In a safe test environment, capture enrollment and a subsequent attendance verification on supported iOS and Android devices. Confirm the open/closed/reopened frames remain correctly ordered, face matching and liveness still pass, request bodies fit the serving PHP and proxy limits, and no attendance record or baseline replacement occurs after rejected image/provider failures. Inspect only dimensions and byte counts; do not log or retain image contents. Exercise retry behavior with the same attendance event ID and verify no duplicate event is created.

Before rollout, record the actual serving PHP/proxy settings, preserve and verify baseline-photo references, and document a backup/restore and application rollback procedure. No production deployment or data change was performed for this patch.
