# M5 Revision 3

## Fixed

- Corrected `AppointmentTypeDeletionTest::test_index_shows_delete_only_for_unused_type_and_disable_for_used_type`.
- The old assertion searched for the bare destroy URL of a used appointment type. That URL is also a prefix of legitimate edit/disable URLs, so the test could fail even when no Delete form was rendered.
- The regression test now checks form semantics: an unused type must have a form targeting its resource URL with `_method=DELETE`; a used type must not have such a DELETE form and must still expose the disable URL.

There are no production-code, database, or migration changes in this revision.
