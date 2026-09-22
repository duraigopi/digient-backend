<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Services\BoardAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function __construct(private BoardAccess $access)
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

        // Step 4: eager-load columns -> cards. Eloquent issues one query per
        // relation (2 extra queries total), never one per column.
        $board->load(['columns', 'columns.cards']);

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
