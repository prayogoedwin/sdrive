<?php

namespace App\Livewire;

use App\Enums\AccessRequestStatus;
use App\Models\DriveAccessRequest;
use App\Services\DriveService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.drive')]
class AccessRequests extends Component
{
    public string $notice = '';

    public function grant(int $id, DriveService $drive): void
    {
        $drive->grantAccess(auth()->user(), $this->ownedRequest($id));
        $this->notice = 'Akses mengubah diberikan.';
    }

    public function deny(int $id, DriveService $drive): void
    {
        $drive->denyAccess(auth()->user(), $this->ownedRequest($id));
        $this->notice = 'Permintaan ditolak.';
    }

    public function render()
    {
        return view('livewire.access-requests', [
            'requests' => $this->rows(),
        ])->layout('layouts.drive', [
            'title' => 'Permintaan akses — SDrive',
        ]);
    }

    private function ownedRequest(int $id): DriveAccessRequest
    {
        $request = DriveAccessRequest::query()->with('item')->findOrFail($id);
        abort_unless($request->item && (int) $request->item->owner_id === (int) auth()->id(), 403);

        return $request;
    }

    /**
     * @return Collection<int, DriveAccessRequest>
     */
    private function rows(): Collection
    {
        return DriveAccessRequest::query()
            ->with(['item:id,name,type,owner_id', 'user:id,name,email,role,status'])
            ->whereHas('item', fn ($query) => $query->where('owner_id', auth()->id()))
            ->orderByRaw('case when status = ? then 0 else 1 end', [AccessRequestStatus::Pending->value])
            ->latest()
            ->limit(50)
            ->get();
    }
}
