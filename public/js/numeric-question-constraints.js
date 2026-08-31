(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.NumericQuestionConstraints = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    // Keep decimal-text comparison in sync with NumericComparison.php.
    function parts(value) {
        if (typeof value !== 'string' && typeof value !== 'number') return null;
        const text = String(value).trim();
        if (text.length > 255) return null;
        const match = /^([+-]?)(\d+(?:\.\d*)?|\.\d+)(?:[eE]([+-]?\d+))?$/.exec(text);
        if (!match) return null;
        const exponentText = match[3] || '0';
        if (exponentText.replace(/^[+\-0]+/, '').length > 4 || Math.abs(Number(exponentText)) > 1000) return null;
        const [integer, fraction = ''] = match[2].split('.');
        const allDigits = integer + fraction, digits = allDigits.replace(/^0+/, '');
        if (!digits) return { sign: 0, digits: '0', power: 0 };
        return {
            sign: match[1] === '-' ? -1 : 1,
            digits: digits.replace(/0+$/, ''),
            power: integer.length - (allDigits.length - digits.length) + Number(exponentText),
        };
    }

    function compare(left, right) {
        const a = parts(left), b = parts(right);
        if (!a || !b) return null;
        if (a.sign !== b.sign) return a.sign > b.sign ? 1 : -1;
        if (a.sign === 0) return 0;
        let magnitude = a.power === b.power ? 0 : (a.power > b.power ? 1 : -1);
        if (magnitude === 0) {
            const length = Math.max(a.digits.length, b.digits.length);
            const x = a.digits.padEnd(length, '0'), y = b.digits.padEnd(length, '0');
            magnitude = x === y ? 0 : (x > y ? 1 : -1);
        }
        return magnitude === 0 ? 0 : a.sign * magnitude;
    }

    function matches(left, operator, right) {
        const result = compare(left, right);
        if (result === null) return false;
        switch (operator) {
            case '>': return result > 0;
            case '>=': return result >= 0;
            case '=': return result === 0;
            case '<=': return result <= 0;
            case '<': return result < 0;
            case '<>': case '!=': case '!': return result !== 0;
            default: return false;
        }
    }

    function attendeeValue(count) {
        if (typeof count !== 'string' && typeof count !== 'number') return null;
        const text = String(count).trim();
        return /^[1-9]\d*$/.test(text) && Number.isSafeInteger(Number(text)) ? text : null;
    }

    function evaluate(value, constraints, readAnswer, attendeeCount = null) {
        if (!constraints.length) return true;
        let completed = false, current = null;
        constraints.forEach(constraint => {
            let right = null;
            switch (constraint.operand_type) {
                case 'question': right = readAnswer(constraint.source_question_uuid); break;
                case 'value': right = constraint.comparison_value; break;
                case 'attendee_count': right = attendeeValue(attendeeCount); break;
            }
            const result = matches(value, constraint.comparison_operator, right);
            if (current === null) current = result;
            else if (constraint.boolean_operator === 'or') { completed = completed || current; current = result; }
            else current = current && result;
        });
        return completed || Boolean(current);
    }

    return { compare, matches, evaluate };
});
