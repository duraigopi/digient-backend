<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Card;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Owns everything that touches the filesystem for attachments.
 *
 * Files live under storage/app/attachments/{board_id}/ — outside the web
 * root, grouped per board so deleting a board is one directory removal.
 * Filenames are random; the user-supplied name is stored only in the DB.
 *
 * Plain PHP filesystem calls rather than the Storage facade: Lumen does
 * not bundle Flysystem and local disk is all this app needs.
 */
class AttachmentStorage
{
    // Allowed types per the assessment: images (jpg, jpeg, png) and documents (docx, pdf).
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf', 'docx'];

    // 10 MB. PHP's own upload_max_filesize/post_max_size must also allow it.
    public const MAX_KILOBYTES = 10240;

    public function store(Card $card, UploadedFile $file, User $uploader): Attachment
    {
        // Extension comes from the content-sniffed MIME type (fileinfo), not
        // from the client filename, so "evil.exe" renamed to "x.png" is rejected
        // by validation and can never be stored with a trusted extension.
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $relativeDir = 'attachments/'.$card->board_id;
        $absoluteDir = $this->basePath().'/'.$relativeDir;

        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0755, true);
        }

        // Read size/mime before move(): the temp file is gone afterwards.
        $size = $file->getSize();
        $mime = $file->getMimeType();

        $file->move($absoluteDir, $name);

        return Attachment::create([
            'card_id' => $card->id,
            'uploaded_by' => $uploader->id,
            'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
            'stored_path' => $relativeDir.'/'.$name,
            'mime' => $mime,
            'size' => $size,
        ]);
    }

    public function absolutePath(Attachment $attachment): string
    {
        return $this->basePath().'/'.$attachment->stored_path;
    }

    // Remove the DB row and its file. Missing file is not an error: the row
    // is the source of truth and the goal is "gone either way".
    public function delete(Attachment $attachment): void
    {
        $path = $this->absolutePath($attachment);

        $attachment->delete();

        if (is_file($path)) {
            @unlink($path);
        }
    }

    // Called before a card or column is deleted: FK cascades remove the rows
    // but know nothing about the files, so unlink them first.
    public function deleteFilesForCards(array $cardIds): void
    {
        if ($cardIds === []) {
            return;
        }

        Attachment::whereIn('card_id', $cardIds)
            ->pluck('stored_path')
            ->each(function (string $relative) {
                $path = $this->basePath().'/'.$relative;
                if (is_file($path)) {
                    @unlink($path);
                }
            });
    }

    // Called before a board is deleted: everything for a board is in one directory.
    public function deleteBoardDirectory(int $boardId): void
    {
        $dir = $this->basePath().'/attachments/'.$boardId;

        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                @unlink($dir.'/'.$entry);
            }
        }

        @rmdir($dir);
    }

    private function basePath(): string
    {
        return storage_path('app');
    }

    // Keep the display name harmless: strip any directory part and control
    // characters, and cap the length to the column size.
    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

        return mb_substr($name !== '' ? $name : 'file', 0, 255);
    }
}
