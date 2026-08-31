@php
    $numericService = app(\App\Domain\Questionnaires\NumericQuestionConstraintService::class);
    $storedNumericConstraints = $question ? $numericService->publicRules($question) : [];
    $numericRows = old('type') !== null ? old('numeric_constraints', []) : $storedNumericConstraints;
    $numericSources = $numericSourceQuestions->map(fn ($source): array => [
        'uuid' => $source->uuid, 'label' => $source->label, 'position' => $source->position,
    ])->all();
    $numericOperators = collect(\App\Enums\NumericComparisonOperator::cases())->map(fn ($operator): array => [
        'value' => $operator->value, 'label' => $operator->label(),
    ])->all();
@endphp
<div class="section-card conditional" data-types="number" id="numeric-constraint-editor">
    <h2>Numeric answer constraints</h2>
    <p class="muted">Optional. Require this answer to satisfy comparisons with an earlier numeric question or a fixed number. For Q2 &gt;= Q1, choose “&gt;=”, “Earlier numeric answer”, then Q1. These rules validate answers; they do not hide questions.</p>
    <p class="muted">AND joins rules within a group; OR starts an alternative group. Minimum, maximum, step, and required settings still apply. A blank or hidden source answer cannot satisfy a comparison, even “different from”. Leave this answer blank only if it is optional.</p>
    <div id="numeric-constraint-rows">
        @foreach((array) $numericRows as $index => $row)
        <div class="card compact numeric-constraint-row">
            <div class="row three">
                <div class="field"><label class="numeric-join-label">Join with</label><select name="numeric_constraints[{{ $index }}][boolean_operator]" data-numeric-field="boolean_operator"><option value="and" @selected(($row['boolean_operator'] ?? 'and') === 'and')>AND</option><option value="or" @selected(($row['boolean_operator'] ?? 'and') === 'or')>OR</option></select></div>
                <div class="field"><label>This answer must be</label><select name="numeric_constraints[{{ $index }}][comparison_operator]" data-numeric-field="comparison_operator" required>@foreach($numericOperators as $operator)<option value="{{ $operator['value'] }}" @selected((in_array($row['comparison_operator'] ?? '', ['<>', '!'], true) ? '!=' : ($row['comparison_operator'] ?? '>=')) === $operator['value'])>{{ $operator['label'] }}</option>@endforeach</select></div>
                <div class="field"><label>Compare with</label><select name="numeric_constraints[{{ $index }}][operand_type]" data-numeric-field="operand_type"><option value="question" @selected(($row['operand_type'] ?? 'question') === 'question')>Earlier numeric answer</option><option value="value" @selected(($row['operand_type'] ?? '') === 'value')>Fixed number</option></select></div>
            </div>
            <div class="field numeric-source-field"><label>Earlier numeric question</label><select name="numeric_constraints[{{ $index }}][source_question_uuid]" data-numeric-field="source_question_uuid" @disabled(($row['operand_type'] ?? 'question') !== 'question') required><option value="">Choose a question…</option>@foreach($numericSources as $source)<option value="{{ $source['uuid'] }}" @selected(($row['source_question_uuid'] ?? '') === $source['uuid'])>#{{ $source['position'] }} · {{ $source['label'] }}</option>@endforeach</select></div>
            <div class="field numeric-value-field"><label>Comparison number</label><input name="numeric_constraints[{{ $index }}][comparison_value]" type="number" step="any" data-numeric-field="comparison_value" value="{{ $row['comparison_value'] ?? '' }}" @disabled(($row['operand_type'] ?? 'question') !== 'value') required></div>
            <button type="button" class="btn btn-danger remove-numeric-constraint">Remove constraint</button>
        </div>
        @endforeach
    </div>
    <button type="button" class="btn" id="add-numeric-constraint">Add numeric constraint</button>
    <p class="muted" id="numeric-constraint-preview" aria-live="polite"></p>
    <p class="muted">Different from can be written as !=, &lt;&gt;, or !; all are saved as !=. Constraints belong to this appointment type, not the reusable template. Remove references before disabling, deleting, changing the type of, or moving a source after its dependent question.</p>
</div>
<script>
(function () {
    const sources = @json($numericSources);
    const operators = @json($numericOperators);
    const rows = document.getElementById('numeric-constraint-rows');
    const add = document.getElementById('add-numeric-constraint');
    const type = document.getElementById('question-type');
    const preview = document.getElementById('numeric-constraint-preview');
    const escape = value => { const el = document.createElement('span'); el.textContent = String(value); return el.innerHTML; };
    const field = (row, name) => row.querySelector(`[data-numeric-field="${name}"]`);
    function refresh() {
        const groups = [], groupRows = Array.from(rows.querySelectorAll('.numeric-constraint-row'));
        let group = [];
        groupRows.forEach((row, index) => {
            row.querySelectorAll('[data-numeric-field]').forEach(control => {
                control.name = `numeric_constraints[${index}][${control.dataset.numericField}]`;
                control.disabled = type.value !== 'number';
            });
            const connector = field(row, 'boolean_operator'), operand = field(row, 'operand_type');
            const source = field(row, 'source_question_uuid'), value = field(row, 'comparison_value');
            connector.style.display = index === 0 ? 'none' : '';
            row.querySelector('.numeric-join-label').textContent = index === 0 ? 'Require' : 'Join with';
            if (index === 0) connector.value = 'and';
            source.disabled = type.value !== 'number' || operand.value !== 'question';
            value.disabled = type.value !== 'number' || operand.value !== 'value';
            row.querySelector('.numeric-source-field').style.display = operand.value === 'question' ? '' : 'none';
            row.querySelector('.numeric-value-field').style.display = operand.value === 'value' ? '' : 'none';
            if (connector.value === 'or' && group.length) { groups.push(`(${group.join(' AND ')})`); group = []; }
            const right = operand.value === 'value' ? (value.value || '[number]') : `“${sources.find(q => q.uuid === source.value)?.label || 'select question'}”`;
            group.push(`this answer ${field(row, 'comparison_operator').value} ${right}`);
        });
        if (group.length) groups.push(`(${group.join(' AND ')})`);
        preview.textContent = groups.length ? 'Required: ' + groups.join(' OR ') : 'No additional numeric constraints.';
        add.disabled = type.value !== 'number' || groupRows.length >= 100;
    }
    add.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'card compact numeric-constraint-row';
        row.innerHTML = `<div class="row three">
            <div class="field"><label class="numeric-join-label">Join with</label><select data-numeric-field="boolean_operator"><option value="and">AND</option><option value="or">OR</option></select></div>
            <div class="field"><label>This answer must be</label><select data-numeric-field="comparison_operator" required>${operators.map(op => `<option value="${escape(op.value)}" ${op.value === '>=' ? 'selected' : ''}>${escape(op.label)}</option>`).join('')}</select></div>
            <div class="field"><label>Compare with</label><select data-numeric-field="operand_type"><option value="question">Earlier numeric answer</option><option value="value">Fixed number</option></select></div>
            </div><div class="field numeric-source-field"><label>Earlier numeric question</label><select data-numeric-field="source_question_uuid" required><option value="">Choose a question…</option>${sources.map(q => `<option value="${q.uuid}">#${q.position} · ${escape(q.label)}</option>`).join('')}</select></div>
            <div class="field numeric-value-field"><label>Comparison number</label><input type="number" step="any" data-numeric-field="comparison_value" required></div>
            <button type="button" class="btn btn-danger remove-numeric-constraint">Remove constraint</button>`;
        if (!sources.length) field(row, 'operand_type').value = 'value';
        rows.appendChild(row);
        refresh();
    });
    rows.addEventListener('click', event => {
        if (event.target.classList.contains('remove-numeric-constraint')) { event.target.closest('.numeric-constraint-row').remove(); refresh(); }
    });
    rows.addEventListener('change', refresh);
    rows.addEventListener('input', refresh);
    document.addEventListener('question-type-toggled', refresh);
    refresh();
})();
</script>
