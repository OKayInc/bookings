const { test } = require('node:test');
const assert = require('node:assert/strict');
const engine = require('../../public/js/numeric-question-constraints.js');
const fixtures = require('../Fixtures/numeric-comparisons.json');

for (const [index, [left, right, expected]] of fixtures.entries()) {
    test(`decimal comparison and all operators: ${index + 1}`, () => {
        assert.equal(engine.compare(left, right), expected);
        assert.equal(engine.compare(right, left), expected === null ? null : (expected === 0 ? 0 : -expected));
        const results = {
            '>': expected > 0, '>=': expected >= 0, '=': expected === 0,
            '<=': expected <= 0, '<': expected < 0,
            '!=': expected !== 0, '<>': expected !== 0, '!': expected !== 0,
        };
        for (const [operator, result] of Object.entries(results)) {
            assert.equal(engine.matches(left, operator, right), expected !== null && result, operator);
        }
    });
}

const rule = (operator, value, join = 'and') => ({
    comparison_operator: operator, comparison_value: value, boolean_operator: join, operand_type: 'value',
});

test('AND binds more tightly than OR, including a trailing AND group', () => {
    // (x >= 10 AND x <= 20) OR (x = 0 AND x != 5)
    const rules = [rule('>=', '10'), rule('<=', '20'), rule('=', '0', 'or'), rule('!=', '5')];
    for (const value of ['10', '15', '20', '0']) assert.equal(engine.evaluate(value, rules, () => null), true);
    for (const value of ['9', '21', '5']) assert.equal(engine.evaluate(value, rules, () => null), false);
    assert.equal(engine.evaluate('15', [rule('=', '15'), rule('=', '0', 'or'), rule('>', '100')], () => null), true);
});

test('missing, hidden, invalid and changed sources are evaluated without coercion', () => {
    const rules = [{ comparison_operator: '>=', operand_type: 'question', source_question_uuid: 'q1', boolean_operator: 'and' }];
    assert.equal(engine.evaluate('5', rules, () => '5'), true);
    assert.equal(engine.evaluate('5', rules, () => '6'), false);
    for (const value of [null, '', 'abc', [], false]) assert.equal(engine.evaluate('5', rules, () => value), false);
    rules[0].comparison_operator = '!';
    assert.equal(engine.evaluate('5', rules, () => null), false);
    assert.equal(engine.evaluate('5', [...rules, rule('=', '5', 'or')], () => null), true);
});

test('oversized numbers fail closed', () => {
    assert.equal(engine.compare('9'.repeat(256), '1'), null);
    assert.equal(engine.matches('1', '==', '1'), false);
});

test('attendee count supports every operator without reading a question answer', () => {
    for (const [operator, valid, invalid] of [
        ['>', '4', '3'], ['>=', '3.0', '2'], ['=', '3', '4'], ['<=', '3', '4'],
        ['<', '2', '3'], ['!=', '2', '3.0'], ['<>', '2', '3'], ['!', '4', '3'],
    ]) {
        const rules = [{ operand_type: 'attendee_count', comparison_operator: operator, boolean_operator: 'and' }];
        const noQuestion = () => { throw Error('Attendees must not be read from questionnaire answers.'); };
        assert.equal(engine.evaluate(valid, rules, noQuestion, 3), true, operator);
        assert.equal(engine.evaluate(invalid, rules, noQuestion, '3'), false, operator);
    }
});

test('attendee count combines with question and fixed operands in AND/OR groups', () => {
    const rules = [
        { operand_type: 'question', source_question_uuid: 'q1', comparison_operator: '>=', boolean_operator: 'and' },
        { operand_type: 'attendee_count', comparison_operator: '<=', boolean_operator: 'and' },
        rule('=', '0', 'or'),
    ];
    for (const value of ['0', '2', '3']) assert.equal(engine.evaluate(value, rules, () => '2', 3), true);
    for (const value of ['1', '4']) assert.equal(engine.evaluate(value, rules, () => '2', 3), false);
});

test('missing or invalid attendee count never becomes zero or a fixed-value fallback', () => {
    const rules = [{ operand_type: 'attendee_count', comparison_operator: '!=', comparison_value: '999', boolean_operator: 'and' }];
    for (const count of [undefined, null, '', 0, -1, 2.5, 'abc', false, [], ['3'], {}, Infinity]) {
        assert.equal(engine.evaluate('3', rules, () => '999', count), false);
    }
});
