# M7-R16-R1 changes

M7-R16-R1 fixes the strict fallback-increment persistence assertion reported in `QuestionnaireConfigurationTest`.

## Cause

The test submitted a fallback increment of `5` and expected the decoded JSON value to be the PHP float `5.0`. MariaDB may normalize a whole-number JSON value and return it as the PHP integer `5`. Both representations have the same configured distance increment and are accepted by the production calculator, but `assertSame` also compares PHP types.

## Fix

- The test casts the persisted increment to `float` before strictly comparing it with `5.0`.
- The assertion continues to require the exact expected numeric value.
- Runtime configuration, validation, fee calculation, and stored booking price lines are unchanged.

There is no database migration, dependency, environment-variable, queue, scheduled-command, or production source change.
