<?php

namespace Tests\Feature;

use App\Enums\SharePermission;
use App\Livewire\AccessRequests;
use App\Livewire\DriveBrowser;
use App\Models\DriveAccessRequest;
use App\Models\User;
use App\Services\DriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_link_can_grant_view_access_and_the_owner_can_approve_an_edit_request(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $drive = app(DriveService::class);
        $folder = $drive->createFolder($owner, null, 'Tertutup');

        $this->get('/t/rahasia')->assertRedirect(route('login'));

        $linked = $drive->setLinkAccess($owner, $folder, SharePermission::View);

        $this->actingAs($viewer)
            ->get('/t/'.$linked->link_token)
            ->assertRedirect(route('drive', [
                'section' => 'shared',
                'folder' => $folder->id,
            ]));

        Livewire::actingAs($viewer)
            ->test(DriveBrowser::class)
            ->call('startRename', $folder->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(DriveBrowser::class)
            ->call('requestEdit', $folder->id)
            ->assertHasNoErrors();

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(1, $owner->unreadNotifications()->count());

        $request = DriveAccessRequest::query()->firstOrFail();

        Livewire::actingAs($owner)
            ->test(AccessRequests::class)
            ->assertSee($viewer->name)
            ->assertSee('Tertutup')
            ->call('grant', $request->id);

        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());

        Livewire::actingAs($viewer)
            ->test(DriveBrowser::class)
            ->call('startRename', $folder->id)
            ->set('renameName', 'Terbuka')
            ->call('rename')
            ->assertHasNoErrors();

        $this->assertSame('Terbuka', $folder->fresh()->name);

        $token = $linked->link_token;
        $drive->setLinkAccess($owner, $folder->fresh(), null);

        $this->actingAs($viewer)->get('/t/'.$token)->assertNotFound();
    }

    public function test_an_edit_link_lets_the_recipient_change_the_item(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $drive = app(DriveService::class);
        $folder = $drive->createFolder($owner, null, 'Bahan');
        $linked = $drive->setLinkAccess($owner, $folder, SharePermission::Edit);

        $this->actingAs($editor)->get('/t/'.$linked->link_token)->assertRedirect();

        Livewire::actingAs($editor)
            ->test(DriveBrowser::class)
            ->call('startRename', $folder->id)
            ->set('renameName', 'Bahan jadi')
            ->call('rename')
            ->assertHasNoErrors();

        $this->assertSame('Bahan jadi', $folder->fresh()->name);
    }

    public function test_someone_else_cannot_answer_the_owners_access_request(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $drive = app(DriveService::class);
        $folder = $drive->createFolder($owner, null, 'Pribadi');
        $drive->share($owner, $folder, $viewer->email, SharePermission::View);
        $drive->requestEdit($viewer, $folder);

        Livewire::actingAs($other)
            ->test(AccessRequests::class)
            ->assertDontSee('Pribadi')
            ->call('grant', DriveAccessRequest::query()->firstOrFail()->id)
            ->assertForbidden();
    }
}
