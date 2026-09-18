# M7-R12 verification

Verification prepared for this release includes:

- static whitespace/error checks with `git diff --check`;
- review of the Google Routes request against the official `computeRoutes` endpoint, address waypoint shape, API-key header, and narrow `routes.distanceMeters` field mask;
- unit coverage for request payload/headers, successful-result caching, and fail-closed empty routes;
- deterministic unit coverage for fixed fees, kilometer boundaries, range gaps, open-ended ranges, and meter-to-mile conversion;
- feature coverage for authorized configuration, reusable-template copying, persisted form rendering, and overlapping-range rejection;
- end-to-end coverage for public-origin secrecy, live quote pricing, final address/route validation, normalized answer metadata, and immutable `question_distance` price lines;
- compatibility coverage through the optional driving-distance map appended to the existing pricing method signature;
- release patch application against a pristine M7-R11-R1 source tree and byte-for-byte comparison with the M7-R12 full package during packaging;
- release ZIP integrity and SHA-256 manifest verification during packaging.

This packaging environment does not contain PHP, Composer dependencies, MariaDB, or Memcached, so PHP syntax lint and the Laravel test suite cannot run here. Run the commands in `UPGRADE-M7-R11-R1-TO-M7-R12.md` on the deployment/test host before production rollout.
