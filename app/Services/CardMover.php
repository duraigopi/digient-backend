<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Column;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a card to (column, position) and keeps every affected column's
 * positions contiguous (0..n-1). Positions are integers renumbered on
 * write rather than fractional ranks: simpler to reason about, no
 * periodic rebalancing, and cheap at realistic column sizes.
 *
 * Runs in one transaction with row locks so two members dragging at the
 * same time cannot interleave and leave duplicate or missing positions.
 */
class CardMover
{
    public function move(Card $card, int $targetColumnId, int $targetPosition): Card
    {
        return DB::transaction(function () use ($card, $targetColumnId, $targetPosition) {
            $sourceColumnId = $card->column_id;

            // Lock the column rows (in id order to avoid deadlocks between two
            // opposite moves) so concurrent inserts/moves in these columns wait.
            $columnIds = array_unique([$sourceColumnId, $targetColumnId]);
            sort($columnIds);
            $columns = Column::whereIn('id', $columnIds)->lockForUpdate()->get()->keyBy('id');

            $target = $columns->get($targetColumnId);

            // A column from another board is a 422, not a 403: the caller
            // already passed the board access check for this card.
            if (! $target || $target->board_id !== $card->board_id) {
                throw ValidationException::withMessages([
                    'column_id' => ['The target column does not belong to this board.'],
                ]);
            }

            // Re-read the card's position under the lock; it may have been
            // shifted by a move that committed after the controller loaded it.
            $card = Card::lockForUpdate()->findOrFail($card->id);

            // 1. Pull the card out of its source column: close the gap.
            Card::where('column_id', $sourceColumnId)
                ->where('position', '>', $card->position)
                ->decrement('position');

            // 2. Clamp the requested slot to what the target column can hold.
            //    The moving card itself no longer counts (it was "removed" above).
            $count = Card::where('column_id', $targetColumnId)
                ->where('id', '!=', $card->id)
                ->count();
            $targetPosition = max(0, min($targetPosition, $count));

            // 3. Open a slot at the target position.
            Card::where('column_id', $targetColumnId)
                ->where('id', '!=', $card->id)
                ->where('position', '>=', $targetPosition)
                ->increment('position');

            // 4. Drop the card into the slot.
            $card->forceFill([
                'column_id' => $targetColumnId,
                'position' => $targetPosition,
            ])->save();

            return $card;
        });
    }
}
