# M7-R3 changes

- Added organization logo upload/removal to Organization create/edit forms.
- Organization logos use a dedicated configurable storage directory and disk.
- Replacing/removing a logo deletes the previous stored file.
- Added `Organization::logo_url` accessor.
- Backend navbar shows the active organization's logo.
- Public organization navbar shows the organization's logo.
- Public appointment cards/detail/password pages fall back to the organization logo whenever the appointment type has no custom logo.
- No database migration is required: `organizations.logo_path` already existed in the original schema.
