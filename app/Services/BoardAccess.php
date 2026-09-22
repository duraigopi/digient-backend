<?php

namespace App\Services;

use App\Models\Board;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Single place where board authorisation is decided. Controllers call
 * one of the assert* methods before touching a board or anything inside
 * it, so the rules are easy to audit and to extend (e.g. a viewer role).
 *
 * Every failure throws AuthorizationException, which the exception
 * handler renders as a 403 JSON response.
 */
class BoardAccess
{
    // Owner or member may read the board.
    public function canView(User $user, Board $board): bool
    {
        if ($board->isOwnedBy($user)) {
            return true;
        }

        return $board->members()->where('users.id', $user->id)->exists();
    }

    // Today every member is an editor; kept separate from canView so a
    // read-only role can be introduced without touching the controllers.
    public function canEdit(User $user, Board $board): bool
    {
        return $this->canView($user, $board);
    }

    public function assertCanView(User $user, Board $board): void
    {
        if (! $this->canView($user, $board)) {
            throw new AuthorizationException('You do not have access to this board.');
        }
    }

    public function assertCanEdit(User $user, Board $board): void
    {
        if (! $this->canEdit($user, $board)) {
            throw new AuthorizationException('You cannot edit this board.');
        }
    }

    // Sharing, renaming and deleting are owner-only operations.
    public function assertOwner(User $user, Board $board): void
    {
        if (! $board->isOwnedBy($user)) {
            throw new AuthorizationException('Only the board owner can do this.');
        }
    }
}
