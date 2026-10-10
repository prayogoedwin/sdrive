<?php

namespace App\Http\Controllers;

use App\Models\DriveItem;
use App\Services\DriveService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriveFileController extends Controller
{
    public function __construct(private DriveService $drive) {}

    public function download(Request $request, DriveItem $item): BinaryFileResponse|StreamedResponse
    {
        return $this->drive->download($request->user(), $item);
    }

    public function preview(Request $request, DriveItem $item): BinaryFileResponse
    {
        return $this->drive->preview($request->user(), $item);
    }
}
