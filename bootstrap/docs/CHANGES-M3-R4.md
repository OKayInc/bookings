# M3 Revision 4

## Fixed organization currency feature test

`OrganizationCurrencyTest::test_edit_organization_uses_supported_currency_dropdown()` previously asserted an exact serialized `<option>` tag. That made the test depend on Blade's precise HTML attribute rendering even though the page was functionally correct.

The test now verifies:

- the currency `<select>` exists;
- CAD, MXN, and USD labels are rendered;
- the CAD `<option>` is actually marked `selected`, using a tolerant regular expression that accepts valid Blade/HTML attribute serialization.

There are no application-code, schema, or migration changes in this revision.
