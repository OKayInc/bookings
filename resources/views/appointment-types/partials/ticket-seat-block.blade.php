<div class="card compact ticket-seat-block" data-ticket-seat-block data-index="{{ $blockIndex }}" style="margin-top:1rem">
    <div class="row three">
        <div class="field" data-ticket-section-field>
            <label for="ticket_section_{{ $blockIndex }}">Section</label>
            <input id="ticket_section_{{ $blockIndex }}" name="ticket_seat_blocks[{{ $blockIndex }}][section]" maxlength="80" value="{{ $block['section'] ?? '' }}" placeholder="Main floor">
        </div>
        <div class="field" data-ticket-row-field>
            <label for="ticket_row_{{ $blockIndex }}">Row</label>
            <input id="ticket_row_{{ $blockIndex }}" name="ticket_seat_blocks[{{ $blockIndex }}][row]" maxlength="80" value="{{ $block['row'] ?? '' }}" placeholder="A">
        </div>
        <div class="field" data-ticket-quantity-field>
            <label for="ticket_quantity_{{ $blockIndex }}">Quantity without seat numbers</label>
            <input id="ticket_quantity_{{ $blockIndex }}" type="number" min="1" name="ticket_seat_blocks[{{ $blockIndex }}][quantity]" value="{{ $block['quantity'] ?? '' }}">
        </div>
    </div>
    <div class="row">
        <div class="field" data-ticket-first-seat-field>
            <label for="ticket_first_seat_{{ $blockIndex }}">First seat number</label>
            <input id="ticket_first_seat_{{ $blockIndex }}" type="number" min="1" name="ticket_seat_blocks[{{ $blockIndex }}][first_seat]" value="{{ $block['first_seat'] ?? '' }}" placeholder="1">
        </div>
        <div class="field" data-ticket-last-seat-field>
            <label for="ticket_last_seat_{{ $blockIndex }}">Last seat number</label>
            <input id="ticket_last_seat_{{ $blockIndex }}" type="number" min="1" name="ticket_seat_blocks[{{ $blockIndex }}][last_seat]" value="{{ $block['last_seat'] ?? '' }}" placeholder="100">
        </div>
    </div>
    <button class="btn btn-outline-danger" type="button" data-remove-ticket-seat-block>Remove seating block</button>
</div>
