# M7-R11-R1 changes

M7-R11-R1 fixes the Laravel 13 Blade compilation failure in the appointment-type create/edit form introduced by M7-R11.

## Cause

The short-notice fee editor initialized its row collection and per-row adjustment type with inline `@php(...)` assignments. In this form, Laravel 13's Blade compiler left the following markup inside the generated PHP section, causing a parse error such as `unexpected identifier "id"` when the appointment-type editor rendered.

## Fix

- Both assignments now use explicit `@php` / `@endphp` blocks with terminated PHP statements.
- The existing appointment-type edit regression test now asserts that the short-notice fee editor renders.
- Short-notice configuration coverage now opens the edit page after saving fixed and percentage tiers and verifies that both persisted values render correctly.

There is no database migration, dependency, environment-variable, queue, or scheduled-command change. All M7-R11 fee selection and pricing semantics remain unchanged.
