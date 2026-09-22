<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\Column;
use App\Services\AttachmentStorage;
use App\Services\BoardAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ColumnController extends Controller
{
    // AttachmentStorage added in Step 5 so destroy() can remove card files.
    public function __construct(private BoardAccess $access, private AttachmentStorage $storage)
    {
    }

    // Append a new column to the end of the board.
    public function store(Request $request, int $boardId): JsonResponse
    {
        $board = Board::findOrFail($boardId);
        $this->access->assertCanEdit($request->user(), $board);

        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
        ]);

        // Lock the board row so two simultaneous "add column" calls cannot
        // both compute the same next position.
        $column = DB::transaction(function () use ($board, $data) {
            Board::whereKey($board->id)->lockForUpdate()->first();

            // max() is null for an empty board -> first column gets position 0.
            $max = Column::where('board_id', $board->id)->max('position');

            return Column::create([
                'board_id' => $board->id,
                'name' => $data['name'],
                'position' => $max === null ? 0 : $max + 1,
            ]);
        });

        $column->setRelation('cards', collect());

        return response()->json($column, 201);
    }

    // Rename a column.
    public function update(Request $request, int $id): JsonResponse
    {
        $column = Column::findOrFail($id);
        $this->access->assertCanEdit($request->user(), $column->board);

        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
        ]);

        $column->update($data);

        return response()->json($column);
    }

    // Delete a column and (via FK cascade) every card in it.
    public function destroy(Request $request, int $id): JsonResponse
    {
        $column = Column::findOrFail($id);
        $this->access->assertCanEdit($request->user(), $column->board);

        // Step 5: unlink attachment files before the FK cascade deletes their rows.
        $this->storage->deleteFilesForCards($column->cards()->pluck('id')->all());

        // Column positions may keep gaps after a delete: ordering still works
        // and there is no column-reorder feature that depends on contiguity.
        $column->delete();

        return response()->json(['message' => 'Column deleted.']);
    }
}
