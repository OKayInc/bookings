# M9-R8 photo galleries and CDN URLs

M9-R8 adds public photo galleries owned independently by an organization or by one appointment type.

## Gallery behavior

- Organization administrators upload gallery photos from the organization editor. Scheduling managers upload appointment photos from the appointment-type editor.
- Every upload chooses **Above the main content** or **Below the main content**. Organization photos wrap the public appointment catalog; appointment photos wrap the appointment detail and booking scheduler.
- Public galleries use a three-column square-crop grid. Selecting a tile opens the complete image in a native dialog with previous/next buttons, arrow-key navigation, Escape, and backdrop closing.
- JPEG, PNG, and WebP inputs are accepted. The server applies EXIF orientation when the selected encoder supports it, downsizes oversized images while preserving aspect ratio, strips metadata, and writes WebP files named by their SHA-256 content hash.
- Duplicate image content is not added twice to the same gallery. Database rows retain dimensions, byte size, digest, disk, and path.

## Plan caps

`organizations.plan_tier` is introduced with `free` as the safe default. It selects separate environment-backed caps for the organization gallery and for each individual appointment-type gallery. This is only the M9-R8 capability hook; full plan administration remains scheduled for M11.

The upload service locks the owning organization while counting and inserting. Browser hints are not trusted, so simultaneous or forged requests cannot exceed the configured cap.

## WebP audit

`gallery:normalize-images` checks the actual file content, not only its extension. Non-WebP content is converted, its database path and metadata are updated, and the original is deleted only after the replacement record commits. WebP content with a non-WebP extension is renamed without lossy re-encoding. Missing or invalid files are reported and do not stop the remaining scan.

The Laravel Scheduler runs this command every Monday at 02:30 in the application timezone. `--dry-run` reports required repairs without changing files.

## CDN behavior

Gallery paths remain storage-relative in MariaDB. With `CDN_ENABLED=true`, the public disk generates URLs from `CDN_URL`, such as `https://images.appointment.to/storage/...`. Disabling the CDN immediately returns URL generation to `APP_URL` after configuration caches are cleared. Hash-based filenames make long-lived immutable CDN caching safe.

Organization deletion, unused appointment-type deletion, and individual photo deletion remove their physical gallery files as well as database rows.

## Video decision

M9-R8 intentionally supports photos only. Video requires a separate design for upload size, transcoding profiles, poster frames, streaming/range requests, bandwidth limits, and CDN behavior. Treating an original video as a large gallery file would create unpredictable client performance and storage costs.
