# Gallery upload and ordering update

This full project archive is based on the latest Appointment.to M10 archive,
including its existing deposit and email-template changes.

## Changes

- Select multiple images as before; the browser now uploads one image per request
  in selection order, reducing aggregate POST size and conversion memory usage.
- The upload form checks selection count and the effective PHP/application
  per-file limit, with room reserved for multipart form fields.
- Progress reports the current file. On failure the queue stops, confirmed uploads
  remain saved, and a refresh link lets you review the gallery before retrying.
- PHP POST-size exceptions return a friendly HTTP 413 page or JSON response,
  including when application debug is enabled. Web-server-generated 413 responses
  are handled by the upload UI, but cannot be customized by Laravel.
- Existing organization and appointment-type photos can be placed above or below
  the main content. Set a numerical order, or use Earlier, Later, First, and Last.
  Ordering is independent within each placement and gallery. Existing permissions
  apply; moving photos does not alter their image files.

## Installation

Deploy the updated project files, preserving your existing .env and uploaded
storage files, then run:

```sh
php artisan optimize:clear
```

No new database migrations are introduced by this gallery update.
JavaScript must be enabled for one-file-at-a-time uploading. Without it, the
standard multipart form remains available and its total size must fit PHP limits.

Individual photos must still fit the server limits. To allow the application's
existing 20 MiB maximum, configure the site's PHP settings (for example, through
ISPConfig's PHP settings) to at least:

```ini
upload_max_filesize = 24M
post_max_size = 32M
```

Apply/reload the site's PHP configuration as required by your host. These are
server settings, not Laravel .env settings; this update does not change the live
server. Any reverse-proxy or web-server body limit must also allow the request.
Resizing to WebP occurs after PHP receives the image and cannot bypass those limits.

## Validation

Five gallery JavaScript tests pass (lightbox, six separate uploads, partial failure,
file-size validation, selection-count validation). Page-loader tests also pass.
Laravel tests were added for reordering/placement, tenant authorization, invalid
positions, JSON upload acknowledgements and friendly HTTP 413 responses. They
could not be executed in the build environment because PHP is not installed.
Run against your configured test database:

```sh
php artisan test --filter=M9R8GalleryTest
```
