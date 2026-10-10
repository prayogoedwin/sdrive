<?php

namespace Tests\Feature;

use App\Enums\DriveSpace;
use App\Enums\SharePermission;
use App\Livewire\DriveBrowser;
use App\Models\User;
use App\Services\DriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DriveAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_their_files_and_files_shared_with_them(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $stranger = User::factory()->create();
        $drive = app(DriveService::class);

        $folder = $drive->createFolder($owner, null, 'Rahasia');
        $drive->createTextFile($owner, $folder->id, 'Catatan.txt', 'isi rahasia');

        $this->actingAs($stranger)->get('/?folder='.$folder->id)->assertForbidden();

        $drive->share($owner, $folder, $viewer->email, SharePermission::View);

        $this->actingAs($viewer)
            ->get('/?section=shared&folder='.$folder->id)
            ->assertOk()
            ->assertSee('Catatan.txt');

        Livewire::actingAs($viewer)
            ->test(DriveBrowser::class)
            ->call('startRename', $folder->id)
            ->assertForbidden();
    }

    public function test_common_drive_is_open_to_every_active_user_without_sharing(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $drive = app(DriveService::class);

        $folder = $drive->createFolder($owner, null, 'Ruang GMT', 'name', DriveSpace::Common);
        $file = $drive->createTextFile($owner, $folder->id, 'Catatan.txt', 'bersama');

        $this->actingAs($other)->get('/?section=common')->assertOk()->assertSee('Ruang GMT');
        $this->actingAs($other)->get('/')->assertOk()->assertDontSee('Ruang GMT');

        Livewire::actingAs($other)
            ->test(DriveBrowser::class)
            ->call('showSection', 'common')
            ->call('startRename', $file->id)
            ->set('renameName', 'Catatan bersama.txt')
            ->call('rename')
            ->assertHasNoErrors();

        $this->assertSame('Catatan bersama.txt', $file->fresh()->name);
        $this->assertTrue($file->fresh()->isCommon());

        $personal = $drive->createFolder($owner, null, 'Pribadi');
        $drive->move($owner, $personal, null, DriveSpace::Common);

        $this->actingAs($other)->get('/?section=common')->assertOk()->assertSee('Pribadi');
        $this->actingAs($other)->get('/')->assertOk()->assertDontSee('Pribadi');

        try {
            $drive->move($other, $personal->fresh(), null, DriveSpace::Personal);
            $this->fail('A non-owner should not pull an item out of Drive Bersama.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('move', $exception->errors());
        }

        $this->assertTrue($personal->fresh()->isCommon());
    }

    public function test_common_drive_uploads_do_not_use_the_personal_quota(): void
    {
        $owner = User::factory()->create(['storage_quota' => 10]);
        $drive = app(DriveService::class);

        $file = $drive->storeUpload(
            $owner,
            null,
            UploadedFile::fake()->create('besar.sqlite', 20),
            DriveSpace::Common,
        );

        $this->assertTrue($file->isCommon());
        $this->assertSame(0, $drive->usage($owner));

        try {
            $drive->storeUpload($owner, null, UploadedFile::fake()->create('pribadi.bin', 20));
            $this->fail('A personal upload should still stop at the quota.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('uploads', $exception->errors());
        }
    }

    public function test_moving_a_common_file_into_my_drive_uses_the_quota(): void
    {
        $owner = User::factory()->create(['storage_quota' => 1024]);
        $drive = app(DriveService::class);
        $file = $drive->storeUpload(
            $owner,
            null,
            UploadedFile::fake()->create('besar.bin', 20),
            DriveSpace::Common,
        );

        try {
            $drive->move($owner, $file, null, DriveSpace::Personal);
            $this->fail('Moving into Drive saya should stop at the quota.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('move', $exception->errors());
        }

        $this->assertTrue($file->fresh()->isCommon());

        $owner->forceFill(['storage_quota' => 30 * 1024])->save();
        $drive->move($owner, $file->fresh(), null, DriveSpace::Personal);

        $this->assertSame(DriveSpace::Personal, $file->fresh()->space);
        $this->assertSame($file->fresh()->size, $drive->usage($owner));
    }

    public function test_editor_can_rename_and_user_cannot_change_owner(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $drive = app(DriveService::class);
        $folder = $drive->createFolder($owner, null, 'Bersama');
        $drive->share($owner, $folder, $editor->email, SharePermission::Edit);

        Livewire::actingAs($editor)
            ->test(DriveBrowser::class)
            ->call('startRename', $folder->id)
            ->set('renameName', 'Terbuka')
            ->call('rename')
            ->assertHasNoErrors();

        $this->assertSame('Terbuka', $folder->fresh()->name);

        Livewire::actingAs($owner)
            ->test(DriveBrowser::class)
            ->call('startOwner', $folder->id)
            ->assertForbidden();
    }

    public function test_admin_manages_every_folder_without_seeing_super_admin_identity(): void
    {
        $super = User::factory()->superAdmin()->create([
            'name' => 'Developer Tersembunyi',
            'email' => 'dev@sdrive.test',
        ]);
        $admin = User::factory()->admin()->create();
        $drive = app(DriveService::class);
        $folder = $drive->createFolder($super, null, 'Folder Sistem');

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Folder Sistem')->assertDontSee('dev@sdrive.test');

        Livewire::actingAs($admin)
            ->test(DriveBrowser::class)
            ->call('startOwner', $folder->id)
            ->set('newOwnerId', $admin->id)
            ->call('changeOwner');

        $this->assertSame($admin->id, $folder->fresh()->owner_id);

        $owned = $drive->createFolder($admin, null, 'Milik Admin');
        try {
            $drive->share($admin, $owned, $super->email, SharePermission::View);
            $this->fail('Admin should not be able to share with a super admin email.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shareEmail', $exception->errors());
        }
    }

    public function test_upload_is_blocked_when_quota_is_full_and_strangers_cannot_download(): void
    {
        $owner = User::factory()->create(['storage_quota' => 10]);
        $stranger = User::factory()->create();
        $drive = app(DriveService::class);

        try {
            $drive->storeUpload($owner, null, UploadedFile::fake()->create('besar.bin', 20));
            $this->fail('Upload should exceed the quota.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('uploads', $exception->errors());
        }

        $file = $drive->createTextFile(User::factory()->create(), null, 'rahasia.txt', 'x');
        $this->actingAs($stranger)->get(route('drive.download', $file))->assertForbidden();
        $this->actingAs($file->owner)->get(route('drive.download', $file))->assertOk();
    }

    public function test_a_sqlite_file_uploads_into_the_open_folder(): void
    {
        $owner = User::factory()->create();
        $folder = app(DriveService::class)->createFolder($owner, null, 'Dokumen');

        Livewire::actingAs($owner)
            ->test(DriveBrowser::class)
            ->set('folderId', $folder->id)
            ->set('uploads', [
                UploadedFile::fake()->create('cadangan.sqlite', 32, 'application/vnd.sqlite3'),
            ])
            ->assertHasNoErrors();

        $stored = $folder->children()->where('extension', 'sqlite')->first();

        $this->assertNotNull($stored);
        $this->assertSame('cadangan.sqlite', $stored->name);
    }
}
