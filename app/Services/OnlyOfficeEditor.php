<?php

namespace App\Services;

use App\Models\DriveItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class OnlyOfficeEditor
{
    /** @var array<string, string> */
    private const TYPES = [
        'doc' => 'word',
        'docx' => 'word',
        'odt' => 'word',
        'rtf' => 'word',
        'xls' => 'cell',
        'xlsx' => 'cell',
        'ods' => 'cell',
        'csv' => 'cell',
        'ppt' => 'slide',
        'pptx' => 'slide',
        'odp' => 'slide',
    ];

    public function enabled(): bool
    {
        return $this->url() !== '' && $this->secret() !== '';
    }

    public function supports(DriveItem $item): bool
    {
        return $item->isFile() && isset(self::TYPES[strtolower((string) $item->extension)]);
    }

    public function url(): string
    {
        return (string) config('sdrive.onlyoffice.url');
    }

    /**
     * @return array<string, mixed>
     */
    public function config(User $actor, DriveItem $item): array
    {
        $edit = app(DriveAccess::class)->canEdit($actor, $item);
        $extension = strtolower((string) $item->extension);

        $config = [
            'document' => [
                'fileType' => $extension,
                'key' => substr(hash('sha256', $item->id.'|'.$item->updated_at?->getTimestamp().'|'.$item->size), 0, 32),
                'title' => $item->name,
                'url' => URL::signedRoute('office.file', ['item' => $item->id]),
                'permissions' => [
                    'edit' => $edit,
                    'download' => true,
                    'print' => true,
                ],
            ],
            'documentType' => self::TYPES[$extension],
            'editorConfig' => [
                'callbackUrl' => URL::signedRoute('office.callback', ['item' => $item->id]),
                'mode' => $edit ? 'edit' : 'view',
                'lang' => 'id',
                'user' => [
                    'id' => (string) $actor->id,
                    'name' => $actor->name,
                ],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                ],
            ],
            'type' => 'desktop',
            'height' => '100%',
            'width' => '100%',
        ];

        $config['token'] = $this->sign($config);

        return $config;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): string
    {
        $segments = [
            $this->base64Url((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES)),
            $this->base64Url((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
        $segments[] = $this->base64Url(hash_hmac('sha256', $segments[0].'.'.$segments[1], $this->secret(), true));

        return implode('.', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    public function callbackPayload(Request $request): array
    {
        $token = $request->bearerToken();
        if (! is_string($token) || $token === '') {
            $token = $request->input('token');
        }
        if (! is_string($token) || $token === '') {
            abort(403);
        }

        return $this->verify($token);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            abort(403);
        }

        $expected = $this->base64Url(hash_hmac('sha256', $parts[0].'.'.$parts[1], $this->secret(), true));
        if (! hash_equals($expected, $parts[2])) {
            abort(403);
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        if (! is_array($payload)) {
            abort(403);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applySavedFile(DriveItem $item, array $payload): void
    {
        $status = (int) ($payload['status'] ?? 0);
        if (! in_array($status, [2, 6], true)) {
            return;
        }

        $url = (string) ($payload['url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('OnlyOffice tidak mengirim file.');
        }

        $contents = $this->download($url);
        Storage::disk('local')->put((string) $item->storage_path, $contents);
        $item->size = strlen($contents);
        $item->save();
    }

    private function download(string $url): string
    {
        $response = Http::timeout(60)->get($url);
        if (! $response->successful()) {
            $internal = (string) config('sdrive.onlyoffice.internal_url');
            $parts = parse_url($url);
            if ($internal !== '' && isset($parts['path'])) {
                $path = $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '');
                $response = Http::timeout(60)->get($internal.$path);
            }
        }

        if (! $response->successful()) {
            throw new RuntimeException('OnlyOffice tidak mengembalikan file.');
        }

        return $response->body();
    }

    private function secret(): string
    {
        return (string) config('sdrive.onlyoffice.jwt_secret');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
