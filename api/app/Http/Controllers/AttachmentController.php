<?php

namespace App\Http\Controllers;

use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'attachable_type' => ['required', Rule::in(array_keys(Relation::morphMap()))],
            'attachable_id' => ['required', 'ulid'],
        ]);

        return AttachmentResource::collection(
            $ledger->attachments()->where($filters)->latest()->get(),
        );
    }

    public function store(Request $request, Ledger $ledger, #[CurrentUser] User $user): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required', 'file',
                'max:'.config()->integer('ledgerfly.attachments.max_size_kb'),
                'mimes:'.implode(',', config()->array('ledgerfly.attachments.mimes')),
            ],
            'attachable_type' => ['required', Rule::in(['account', 'transaction_group'])],
            'attachable_id' => ['required', 'ulid'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $attachable = $validated['attachable_type'] === 'account'
            ? $ledger->accounts()->findOrFail((string) $validated['attachable_id'])
            : $ledger->transactionGroups()->findOrFail((string) $validated['attachable_id']);

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $path = $file->store("attachments/{$ledger->id}", config()->string('ledgerfly.attachments.disk'));
        abort_if($path === false, 500);

        $attachment = $attachable->attachments()->create([
            'ledger_id' => $ledger->id,
            'uploaded_by' => $user->id,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'title' => $validated['title'] ?? null,
        ]);

        return (new AttachmentResource($attachment))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function download(Ledger $ledger, Attachment $attachment): StreamedResponse
    {
        return Storage::disk(config()->string('ledgerfly.attachments.disk'))
            ->download($attachment->path, $attachment->filename);
    }

    public function destroy(Ledger $ledger, Attachment $attachment): Response
    {
        $attachment->delete();

        return response()->noContent();
    }
}
