# M3 Revision 5

## Registration currency test fix

The registration currency dropdown feature test previously asserted complete `<option>` tags byte-for-byte. Blade's `@selected` directive can leave harmless whitespace in an unselected option tag, making the test fail even though the rendered dropdown is correct.

The regression test now verifies the dropdown semantically:

- the currency `<select>` is present;
- CAD, MXN, and USD labels are rendered;
- CAD is selected by default using a whitespace/attribute-tolerant regular expression.

There are no application or database changes in this revision.
