<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\User;
use App\Services\BoardAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BoardMemberController extends Controller
{
    public function __construct(private BoardAccess $access)
    {
    }

    // Share the board with an existing user, looked up by email (owner only).
    public function store(Request $request, int $boardId): JsonResponse
    {
        $board = Board::findOrFail($boardId);
        $this->access->assertOwner($request->user(), $board);

        $data = $this->validate($request, [
            'email' => 'required|email',
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        // 422 rather than 404 so the frontend can show it as a form error.
        if (! $user) {
            throw ValidationException::withMessages(['email' => ['No user with that email.']]);
        }

        if ($board->isOwnedBy($user)) {
            throw ValidationException::withMessages(['email' => ['The owner is already on this board.']]);
        }

        if ($board->members()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages(['email' => ['That user is already a member.']]);
        }

        $board->members()->attach($user->id);

        return response()->json($this->memberList($board), 201);
    }

    // Remove a member from the board (owner only).
    public function destroy(Request $request, int $boardId, int $userId): JsonResponse
    {
        $board = Board::findOrFail($boardId);
        $this->access->assertOwner($request->user(), $board);

        // detach() returns the number of rows removed; 0 means they were not a member.
        if ($board->members()->detach($userId) === 0) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($this->memberList($board));
    }

    // Both endpoints return the fresh member list so the UI can replace its state in one go.
    private function memberList(Board $board)
    {
        return $board->members()->get(['users.id', 'users.name', 'users.email']);
    }
}
