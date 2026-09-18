const test = require('node:test');
const assert = require('node:assert/strict');
const visibility = require('../../public/js/question-visibility.js');

test('a condition matches any configured answer', () => {
    const selected = new Set(['video']);
    const conditions = [{
        boolean_operator: 'and',
        source_question_uuid: 'services',
        question_option_uuids: ['photo', 'video'],
    }];

    assert.equal(
        visibility.expressionMatches(conditions, (questionUuid, optionUuid) => (
            questionUuid === 'services' && selected.has(optionUuid)
        )),
        true,
    );
});

test('a condition stays false when no configured answer is selected', () => {
    const conditions = [{
        boolean_operator: 'and',
        source_question_uuid: 'services',
        question_option_uuids: ['photo', 'video'],
    }];

    assert.equal(
        visibility.expressionMatches(conditions, (_questionUuid, optionUuid) => optionUuid === 'printing'),
        false,
    );
});

test('multi-answer conditions preserve surrounding AND and OR groups', () => {
    const selected = new Set(['video', 'indoor']);
    const conditions = [
        { boolean_operator: 'and', source_question_uuid: 'services', question_option_uuids: ['photo', 'video'] },
        { boolean_operator: 'and', source_question_uuid: 'location', question_option_uuids: ['indoor'] },
        { boolean_operator: 'or', source_question_uuid: 'priority', question_option_uuids: ['rush'] },
    ];

    assert.equal(
        visibility.expressionMatches(conditions, (_questionUuid, optionUuid) => selected.has(optionUuid)),
        true,
    );
});

test('legacy single-answer condition payload remains supported', () => {
    const conditions = [{
        boolean_operator: 'and',
        source_question_uuid: 'choice',
        question_option_uuid: 'yes',
    }];

    assert.equal(
        visibility.expressionMatches(conditions, (_questionUuid, optionUuid) => optionUuid === 'yes'),
        true,
    );
});
