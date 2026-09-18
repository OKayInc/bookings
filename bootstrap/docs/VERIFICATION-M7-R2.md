# M7-R2 verification

Release checks performed in the packaging environment:

- Reconstructed baseline from M7-R1 plus GitHub commits `0ef10649...` and `0f90c7a...` before applying M7-R2.
- Preserved `public/.htaccess`.
- Preserved session-independent backend email verification behavior and regression test.
- PHP syntax lint run across application/config/routes/migrations/tests.
- OAuth callback route exists only once and is outside authenticated organization middleware.
- Calendar connect route remains inside `auth + verified + organization` middleware.
- Raw OAuth state is not stored in the database; `state_hash` is SHA-256.
- OAuth state is single-use, time-limited, provider-bound, user-bound, organization-bound, and resource-bound.
- Callback re-checks current resource permission.
- MariaDB explicit index/foreign-key names are under 64 characters and newly introduced names are unique.
- Patch is generated from the reconstructed current GitHub repository state, not from the old M7-R1 archive.

The packaging environment does not have the deployment's MariaDB/Memcached services, so run the full Laravel test suite on the hosting/test installation.
