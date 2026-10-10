<?php

namespace App\Http\Controllers;

use App\Services\DriveService;
use Illuminate\Http\RedirectResponse;

class ShareLinkController extends Controller
{
    public function __invoke(string $token, DriveService $drive): RedirectResponse
    {
        $item = $drive->redeemLink(auth()->user(), $token);
        $user = auth()->user();

        if ($item->isCommon()) {
            $section = 'common';
        } elseif ($user->isManager() || (int) $item->owner_id === (int) $user->id) {
            $section = 'my-drive';
        } else {
            $section = 'shared';
        }

        $params = ['section' => $section];

        if ($item->isFolder()) {
            $params['folder'] = $item->id;
        } else {
            $params['open'] = $item->id;
            if ($item->parent_id) {
                $params['folder'] = $item->parent_id;
            }
        }

        return redirect()->route('drive', $params);
    }
}
