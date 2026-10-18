<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use App\Models\CourrierAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourrierAttachmentController extends Controller
{
    public function store(Request $request, Courrier $courrier): RedirectResponse
    {
        Gate::authorize('attach', $courrier);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:10240'],
        ]);
        $file = $data['file'];
        $path = $file->store("courriers/{$courrier->id}", 'local');

        $courrier->attachments()->create([
            'uploaded_by_id' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'La pièce jointe a été ajoutée.');
    }

    public function download(Request $request, CourrierAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->courrier);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, CourrierAttachment $attachment): RedirectResponse
    {
        Gate::authorize('attach', $attachment->courrier);
        abort_unless(
            $request->user()->role === 'admin' || $attachment->uploaded_by_id === $request->user()->id,
            403,
        );

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'La pièce jointe a été supprimée.');
    }
}
