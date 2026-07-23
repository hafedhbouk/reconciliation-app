<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExceptionAttachmentRequest;
use App\Models\ExceptionAttachment;
use App\Models\ExceptionRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExceptionAttachmentController extends Controller
{
    public function store(StoreExceptionAttachmentRequest $request, ExceptionRecord $exception): RedirectResponse
    {
        $this->authorize('update', $exception);

        $file = $request->file('file');
        $path = $file->store("exceptions/{$exception->id}", 'local');

        $exception->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.exceptions.show', $exception)->with('status', __('Pièce jointe ajoutée avec succès.'));
    }

    public function download(ExceptionRecord $exception, ExceptionAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $exception);

        abort_unless($attachment->exception_id === $exception->id, 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(ExceptionRecord $exception, ExceptionAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $exception);

        abort_unless($attachment->exception_id === $exception->id, 404);

        $attachment->delete();

        return redirect()->route('admin.exceptions.show', $exception)->with('status', __('Pièce jointe supprimée avec succès.'));
    }
}
