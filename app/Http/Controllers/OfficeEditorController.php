<?php

namespace App\Http\Controllers;

use App\Models\DriveItem;
use App\Services\DriveAccess;
use App\Services\OnlyOfficeEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OfficeEditorController extends Controller
{
    public function __construct(private OnlyOfficeEditor $office, private DriveAccess $access) {}

    public function show(Request $request, DriveItem $item): View
    {
        abort_unless($this->office->enabled() && $this->office->supports($item), 404);
        abort_unless($this->access->canView($request->user(), $item), 403);

        return view('office.editor', [
            'item' => $item,
            'serverUrl' => $this->office->url(),
            'config' => $this->office->config($request->user(), $item),
            'configUrl' => route('office.config', $item),
        ]);
    }

    public function config(Request $request, DriveItem $item): JsonResponse
    {
        abort_unless($this->office->enabled() && $this->office->supports($item), 404);
        abort_unless($this->access->canView($request->user(), $item), 403);

        return response()->json($this->office->config($request->user(), $item));
    }

    public function file(DriveItem $item): BinaryFileResponse
    {
        abort_unless($item->isFile(), 404);
        $path = (string) $item->storage_path;
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        $name = str_replace(['"', '\\'], '', $item->name);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $item->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function callback(Request $request, DriveItem $item): JsonResponse
    {
        abort_unless($this->office->supports($item), 404);

        try {
            $this->office->applySavedFile($item, $this->office->callbackPayload($request));
        } catch (HttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('OnlyOffice gagal menyimpan berkas.', [
                'item' => $item->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 1]);
        }

        return response()->json(['error' => 0]);
    }
}
