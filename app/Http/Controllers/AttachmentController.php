<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Card;
use App\Services\AttachmentStorage;
use App\Services\BoardAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function __construct(private BoardAccess $access, private AttachmentStorage $storage)
    {
    }

    // Upload one file (jpg/jpeg/png/pdf/docx, <= 10 MB) onto a card.
    public function store(Request $request, int $cardId): JsonResponse
    {
        $card = Card::findOrFail($cardId);
        $this->access->assertCanEdit($request->user(), $card->board);

        // "mimes" checks the sniffed content type, not the client extension.
        $this->validate($request, [
            'file' => 'required|file|mimes:'.implode(',', AttachmentStorage::ALLOWED_EXTENSIONS)
                .'|max:'.AttachmentStorage::MAX_KILOBYTES,
        ]);

        $attachment = $this->storage->store($card, $request->file('file'), $request->user());

        return response()->json($attachment, 201);
    }

    // Stream the file to any board member; the path is never exposed.
    public function download(Request $request, int $id): BinaryFileResponse
    {
        $attachment = Attachment::with('card')->findOrFail($id);
        $this->access->assertCanView($request->user(), $attachment->card->board);

        $path = $this->storage->absolutePath($attachment);

        if (! is_file($path)) {
            abort(404, 'File is missing from storage.');
        }

        // "inline" so images/PDFs can preview in the browser; the frontend
        // adds the download attribute when the user explicitly saves.
        return response()->file($path, [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }

    // Remove the attachment row and its file.
    public function destroy(Request $request, int $id): JsonResponse
    {
        $attachment = Attachment::with('card')->findOrFail($id);
        $this->access->assertCanEdit($request->user(), $attachment->card->board);

        $this->storage->delete($attachment);

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
