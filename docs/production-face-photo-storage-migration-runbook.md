# Production Face-Photo Storage Migration Runbook

**Status: blocked — do not mount a new volume over the current photo directory yet.**

This runbook is an execution gate, not proof that the migration has occurred. The production Railway API service was inspected on 2026-10-15: it has no attached volume, runs one replica in `sfo`, and the MySQL service has a separate volume mounted at `/var/lib/mysql`. The API's Laravel private disk defaults to `storage/app/private`; the optional `FILESYSTEM_PRIVATE_ROOT` setting can redirect that disk.

Laravel local-disk paths are relative to the configured root. Mounting an empty volume at that same root hides files previously present in the container filesystem. A successful code deploy or a green test suite does not establish that production face photos were preserved.

## Non-negotiable safety rules

- Do not attach a new volume at `/app/storage/app/private` (or any other target) until the currently deployed container's existing files have been inventoried and preserved.
- Do not delete or overwrite the old files during the first copy. Keep a verified, access-controlled backup until the new storage has passed end-to-end tests and the release owner approves retirement.
- Do not copy employee photos to a public disk, public URL, repository, chat, or an unapproved personal device.
- Do not log image bytes, base64 images, face embeddings, access tokens, or secrets.
- If the current container has already been replaced and the original files are no longer accessible, stop and document that fact. Do not claim that missing files were migrated or recovered from the database.
- Keep the API at one replica while using a single attached Railway volume. Confirm current Railway platform constraints before changing replica count or region.

## Phase A — establish what exists (no production changes)

1. Record the exact deployed API deployment ID, timestamp, branch/commit, region, and replica count.
2. Use an approved authenticated method to inspect the currently running container **before** any mount change. Determine the actual Laravel private root from the deployed cached config, not from an assumed path.
3. Inventory the database fields that reference photos, including `users.baseline_photo_path` and `attendance_logs.photo_path`. Do not export image contents. Preserve the stored path exactly in the restricted manifest, and normalize separators only for lookup; legacy values may include a `storage/` prefix or an absolute local path.
4. Inventory **both** configured `private` and `public` disks, plus any referenced absolute local paths. The current attendance photo endpoint checks `private` first and then falls back to `public`, and baseline resolution also checks both disks. A private-only inventory could therefore miss legacy photos that the application still depends on. For each file record its disk/source, relative path, byte size, and SHA-256 checksum. Keep the manifest in an access-controlled release record; avoid employee names and photo contents in ordinary logs.
5. Record counts of references, distinct referenced files, existing files, missing references, duplicate path references, unreferenced files, total bytes per source, and any read/permission errors. Investigate every mismatch before continuing.
6. Create a separate encrypted backup/archive of every source location containing referenced photos, including legacy public-disk files if present, using an approved secure transfer process. Verify archive readability and file checksums. Preserve every source in place. Record who performed the backup, when, and where the encrypted backups are stored.

**Stop condition:** If there is no authenticated way to access the running container, no safe export path, or no verifiable copy, do not attach the volume. Escalate to the Railway project owner/operator to arrange preservation first.

## Phase B — prepare a durable target

1. Choose and approve the target: either a Railway persistent volume or a private S3-compatible object store. Do not choose a public bucket or public-read ACL. The current application explicitly reads and writes the Laravel `private` disk, so setting `FILESYSTEM_DISK=s3` alone is not sufficient.
2. For a Railway volume, choose a mount path that does **not** overwrite the old private directory during the preservation phase. Ensure it is in the same supported region as the API, plan capacity above the inventoried bytes with headroom, and attach only after Phase A is signed off.
3. For a volume mounted at `/data/timepay-private`, configure `FILESYSTEM_PRIVATE_ROOT=/data/timepay-private`. Confirm the Laravel private disk resolves to that exact absolute path after config caching. The `.env.example` example path is illustrative; it is not a production instruction unless the actual mount matches.
4. If object storage is selected, implement and test an explicit private-disk configuration using the chosen provider's credentials and access policy. Verify server-side encryption, least-privilege access, lifecycle/retention, backups, and authenticated application access before cutover.
5. Never create the target by mounting an empty volume directly over the old private root before copying. If the intended final mount must use that path, copy and verify into the new volume through a separate staging mount first, then schedule a controlled cutover.

## Phase C — copy and verify before cutover

1. Copy files without deleting the source. Preserve relative paths and file names exactly so existing database references remain valid.
2. Compare source and destination inventories. Require exact file-count and path matches and SHA-256 equality for every copied file. Resolve every mismatch; do not accept a partial copy.
3. Recheck database references against the destination. Require all referenced baseline and attendance photos to exist and be readable. Explain and approve any intentionally orphaned files or historical missing references.
4. Confirm private permissions and ensure files cannot be fetched without authentication/authorization. Avoid public symlinks and public storage URLs.
5. Keep the original source and encrypted backup unchanged through cutover and post-cutover validation.

## Phase D — controlled cutover and acceptance

1. Schedule a short maintenance window if required to avoid concurrent photo writes during the final sync. Record the cutover start and end times.
2. Perform a final incremental copy and checksum verification while writes are quiesced or otherwise safely reconciled.
3. Set `FILESYSTEM_PRIVATE_ROOT` to the verified target path. Do not change unrelated variables. Confirm the Railway diff before applying it.
4. Apply the mount/config change only after explicit release-owner approval; it will redeploy the API. Keep a known-good rollback plan that does not delete or overwrite either copy.
5. After deployment, verify:
   - existing employee enrollment/baseline photos can be read by the authorized verification flow;
   - a new enrollment/baseline photo can be stored and read;
   - attendance selfie upload and face comparison work;
   - authenticated retrieval succeeds and unauthenticated retrieval is denied;
   - authorized deletion works as intended and is reflected in the configured retention policy;
   - a service restart/redeploy does not lose files;
   - backup and restore of the new storage target succeeds.
6. Compare pre-cutover and post-cutover inventories and application-level reference counts. Record test IDs and outcomes without attaching photos to the release record.
7. Keep the old copy and encrypted backup until the release owner signs off, the restore test passes, and the retention/privacy policy permits deletion.

## Evidence required to close this blocker

- [ ] Actual production private root confirmed from the running deployment.
- [ ] Source inventory and encrypted backup recorded and verified before any mount change.
- [ ] All referenced photo files copied; path/count/checksum comparison is clean.
- [ ] Durable target and access policy approved.
- [ ] Railway mount and `FILESYSTEM_PRIVATE_ROOT` configuration match.
- [ ] End-to-end enrollment, verification, authenticated read, deletion, restart, and restore tests pass.
- [ ] Release owner records approval and the backup-retention/removal decision.

Until all evidence above exists, production photo storage remains **unresolved** and deployment remains **NO-GO**.
