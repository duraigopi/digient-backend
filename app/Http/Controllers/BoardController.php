<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Services\AttachmentStorage;
use App\Services\BoardAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    // AttachmentStorage added in Step 5 so destroy() can remove the board's files.
    public function __construct(private BoardAccess $access, private AttachmentStorage $storage)
    {
    }

    // List every board the user owns or has been invited to.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $boards = Board::visibleTo($user)
            ->with('owner:id,name,email')
            ->orderBy('name')
            ->get()
            ->each(fn (Board $b) => $b->setAttribute('is_owner', $b->isOwnedBy($user)));

        return response()->json($boards);
    }

    // Create a board owned by the current user.
    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
        ]);

        $board = Board::create([
            'owner_id' => $request->user()->id,
            'name' => $data['name'],
        ]);

        return response()->json($this->present($board, $request), 201);
    }

    // Return the whole board: owner, members, columns and their cards, in order.
    public function show(Request $request, int $id): JsonResponse
    {
        $board = Board::findOrFail($id);
        $this->access->assertCanView($request->user(), $board);

        // Eager-load columns -> cards -> attachments. Eloquent issues one query
        // per relation (3 extra queries total), never one per column or card.
        $board->load(['columns', 'columns.cards', 'columns.cards.attachments']);

        return response()->json($this->present($board, $request));
    }

    // Rename a board (owner only).
    public function update(Request $request, int $id): JsonResponse
    {
        $board = Board::findOrFail($id);
        $this->access->assertOwner($request->user(), $board);

        $data = $this->validate($request, [
            'name' => 'required|string|max:120',
        ]);

        $board->update($data);

        return response()->json($this->present($board, $request));
    }

    // Delete a board and, via FK cascades, everything inside it (owner only).
    public function destroy(Request $request, int $id): JsonResponse
    {
        $board = Board::findOrFail($id);
        $this->access->assertOwner($request->user(), $board);

        $board->delete();

        // Step 5: FK cascades removed the attachment rows; the files live in
        // one per-board directory, so remove that after the rows are gone.
        $this->storage->deleteBoardDirectory($board->id);

        return response()->json(['message' => 'Board deleted.']);
    }

    // Shared JSON shape for a single board so every endpoint returns the same fields.
    private function present(Board $board, Request $request): Board
    {
        $board->load(['owner:id,name,email', 'members:id,name,email']);
        $board->setAttribute('is_owner', $board->isOwnedBy($request->user()));

        return $board;
    }
}
