# M7-R3-R1 changes

- Fixed `OrganizationLogoTest` to follow the project's existing appointment-type test convention.
- The public fallback-logo regression test now creates its appointment type with `AppointmentType::create(...)` instead of calling a non-existent `Database\\Factories\\AppointmentTypeFactory`.
- No production code, database schema, routes, configuration, or Composer dependencies changed.
