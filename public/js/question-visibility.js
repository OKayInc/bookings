(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.QuestionVisibility = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function expectedOptionUuids(condition) {
        const values = Array.isArray(condition.question_option_uuids)
            ? condition.question_option_uuids
            : [condition.question_option_uuid];

        return [...new Set(values.filter(value => typeof value === 'string' && value !== ''))];
    }

    function expressionMatches(conditions, hasAnswer) {
        if (conditions.length === 0) return true;

        let completed = false;
        let current = null;

        conditions.forEach((condition, index) => {
            const matches = expectedOptionUuids(condition).some(
                optionUuid => hasAnswer(condition.source_question_uuid, optionUuid),
            );

            if (index === 0) current = matches;
            else if (condition.boolean_operator === 'or') {
                completed = completed || current;
                current = matches;
            } else current = current && matches;
        });

        return completed || Boolean(current);
    }

    return { expectedOptionUuids, expressionMatches };
}));
