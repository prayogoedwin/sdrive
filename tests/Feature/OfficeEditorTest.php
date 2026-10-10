<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DriveService;
use App\Services\OnlyOfficeEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OfficeEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sdrive.onlyoffice.url' => 'http://onlyoffice.test',
            'sdrive.onlyoffice.jwt_secret' => 'rahasia-onlyoffice',
        ]);
    }

    public function test_an_editor_can_open_a_docx_and_a_saved_copy_replaces_the_file(): void
    {
        $owner = User::factory()->create();
        $item = app(DriveService::class)->storeUpload(
            $owner,
            null,
            UploadedFile::fake()->create('laporan.docx', 4),
        );
        Storage::disk('local')->put((string) $item->storage_path, 'awal');

        $config = app(OnlyOfficeEditor::class)->config($owner, $item);
        $this->assertSame('edit', $config['editorConfig']['mode']);
        $this->assertSame('word', $config['documentType']);
        $this->assertSame($item->id.'-1', $config['document']['key']);

        $this->actingAs($owner)
            ->get(route('office.show', $item))
            ->assertOk()
            ->assertSee('DocsAPI.DocEditor', false)
            ->assertSee('onRequestRefreshFile', false)
            ->assertSee('onlyoffice.test/web-apps/apps/api/documents/api.js', false);

        Http::fake([
            'http://docs.test/*' => Http::response('isi baru dari onlyoffice'),
        ]);

        $editor = app(OnlyOfficeEditor::class);
        $token = $editor->sign([
            'status' => 2,
            'url' => 'http://docs.test/cache/laporan.docx',
            'key' => 'abc',
        ]);

        $this->postJson(URL::signedRoute('office.callback', ['item' => $item->id]), [
            'token' => $token,
        ])->assertOk()->assertJson(['error' => 0]);

        $this->assertSame('isi baru dari onlyoffice', Storage::disk('local')->get($item->storage_path));
        $saved = $item->fresh();
        $this->assertSame(strlen('isi baru dari onlyoffice'), $saved->size);
        $this->assertSame(2, $saved->office_version);
        $this->assertSame($item->id.'-2', app(OnlyOfficeEditor::class)->config($owner, $saved)['document']['key']);
    }

    public function test_a_forcesave_from_the_document_server_replaces_the_stored_file(): void
    {
        config(['sdrive.onlyoffice.internal_url' => 'http://onlyoffice.internal']);

        $owner = User::factory()->create();
        $item = app(DriveService::class)->storeUpload(
            $owner,
            null,
            UploadedFile::fake()->create('angka.xlsx', 2),
        );
        Storage::disk('local')->put((string) $item->storage_path, 'awal');

        Http::fake([
            'http://127.0.0.1/*' => Http::response('halaman salah', 404),
            'http://onlyoffice.internal/*' => Http::response('angka tersimpan'),
        ]);

        $editor = app(OnlyOfficeEditor::class);
        $token = $editor->sign([
            'payload' => [
                'status' => 6,
                'url' => 'http://127.0.0.1/cache/files/angka.xlsx?md5=abc',
            ],
        ]);

        $this->postJson(URL::signedRoute('office.callback', ['item' => $item->id]), [
            'token' => $token,
        ])->assertOk()->assertJson(['error' => 0]);

        $this->assertSame('angka tersimpan', Storage::disk('local')->get($item->storage_path));
        $this->assertSame(2, $item->fresh()->office_version);
    }

    public function test_a_callback_without_a_valid_token_is_rejected(): void
    {
        $owner = User::factory()->create();
        $item = app(DriveService::class)->storeUpload(
            $owner,
            null,
            UploadedFile::fake()->create('angka.xlsx', 2),
        );

        $this->postJson(URL::signedRoute('office.callback', ['item' => $item->id]), [
            'status' => 2,
            'url' => 'http://docs.test/angka.xlsx',
        ])->assertForbidden();
    }
}
