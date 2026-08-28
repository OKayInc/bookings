# M5 Revision 6

## Test coverage correction

`QuestionnaireConfigurationTest::test_question_with_historical_answers_will_be_disabled_instead_of_deleted`
was previously explicitly skipped with a note claiming the behavior was covered by
`QuestionnaireBookingFlowTest`. That note was inaccurate: the booking-flow test verifies
questionnaire answer and pricing persistence, but did not exercise the question deletion path.

The skipped test is now a real end-to-end feature regression test. It:

1. creates an appointment question;
2. creates an appointment, organization contact, booking, and historical `BookingAnswer` linked to that question;
3. sends the real DELETE request to the questionnaire controller;
4. verifies the controller reports that the question was disabled;
5. verifies the question still exists with `is_active = false`; and
6. verifies the historical answer remains linked and intact.

No production code, schema, or migration changes are included in this revision.
