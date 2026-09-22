<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Column;
use App\Services\AttachmentStorage;
use App\Services\BoardAccess;
use App\Services\CardMover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardController extends Controller
{
    // AttachmentStorage added in Step 5 so destroy() can remove the card's files.
    public function __construct(
        private BoardAccess $access,
        private CardMover $mover,
        private AttachmentStorage $storage,
    ) {
    }

    // Append a new card to the bottom of a column.
    public function store(Request $request, int $columnId): JsonResponse
    {
        $column = Column::findOrFail($columnId);
        $this->access->assertCanEdit($request->user(), $column->board);

        $data = $this->validate($request, [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
        ]);

        // Lock the column row so concurrent adds get distinct positions.
        $card = DB::transaction(function () use ($column, $data) {
            Column::whereKey($column->id)->lockForUpdate()->first();

            return Card::create([
                'board_id' => $column->board_id,
                'column_id' => $column->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'position' => Card::where('column_id', $column->id)->count(),
            ]);
        });

        return response()->json($card, 201);
    }

    // Edit a card's title and/or description (position is changed via move()).
    public function update(Request $request, int $id): JsonResponse
    {
        $card = Card::findOrFail($id);
        $this->access->assertCanEdit($request->user(), $card->board);

        $data = $this->validate($request, [
            'title' => 'sometimes|required|string|max:200',
            'description' => 'sometimes|nullable|string|max:5000',
        ]);

        $card->update($data);

        return response()->json($card);
    }

    // Move a card to a column/position; the service keeps positions contiguous.
    public function move(Request $request, int $id): JsonResponse
    {
        $card = Card::findOrFail($id);
        $this->access->assertCanEdit($request->user(), $card->board);

        $data = $this->validate($request, [
            'column_id' => 'required|integer',
            'position' => 'required|integer|min:0',
        ]);

        $card = $this->mover->move($card, (int) $data['column_id'], (int) $data['position']);

        return response()->json($card);
    }

    // Delete a card and close the gap it leaves in its column.
    public function destroy(Request $request, int $id): JsonResponse
    {
        $card = Card::findOrFail($id);
        $this->access->assertCanEdit($request->user(), $card->board);

        // Step 5: unlink attachment files before the FK cascade deletes their rows.
        $this->storage->deleteFilesForCards([$card->id]);

        DB::transaction(function () use ($card) {
            Column::whereKey($card->column_id)->lockForUpdate()->first();

            $card->delete();

            Card::where('column_id', $card->column_id)
                ->where('position', '>', $card->position)
                ->decrement('position');
        });

        return response()->json(['message' => 'Card deleted.']);
    }
}
