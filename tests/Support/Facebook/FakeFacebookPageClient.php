<?php

namespace Tests\Support\Facebook;

use App\Contracts\FacebookPageClient;
use App\Data\Facebook\FacebookApiResult;
use App\Models\Workspace;
use App\Support\Facebook\FacebookConfig;

/**
 * Records every Graph call. Publishing succeeds with page post id
 * "123_456" unless publishResult is set.
 */
final class FakeFacebookPageClient implements FacebookPageClient
{
    public const PAGE_ID = '1234567890';

    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public ?FacebookApiResult $publishResult = null;

    public ?FacebookApiResult $commentResult = null;

    public ?FacebookApiResult $pageResult = null;

    public static function connect(Workspace $workspace, bool $enabled = true, string $language = 'bn'): void
    {
        FacebookConfig::write($workspace, self::PAGE_ID, 'Amar Page', 'page-token', $enabled, $language);
        $workspace->refresh();
    }

    public function page(string $pageId, string $pageAccessToken): FacebookApiResult
    {
        $this->calls[] = ['method' => 'page', 'page_id' => $pageId, 'token' => $pageAccessToken];

        return $this->pageResult ?? new FacebookApiResult(true, id: $pageId, name: 'Amar Page');
    }

    public function publishPhoto(string $pageId, string $pageAccessToken, string $photoContents, string $filename, string $caption): FacebookApiResult
    {
        $this->calls[] = ['method' => 'photo', 'page_id' => $pageId, 'token' => $pageAccessToken, 'bytes' => strlen($photoContents), 'filename' => $filename, 'caption' => $caption];

        return $this->publishResult ?? new FacebookApiResult(true, id: '999', postId: '123_456');
    }

    public function publishText(string $pageId, string $pageAccessToken, string $message): FacebookApiResult
    {
        $this->calls[] = ['method' => 'text', 'page_id' => $pageId, 'token' => $pageAccessToken, 'caption' => $message];

        return $this->publishResult ?? new FacebookApiResult(true, id: '123_789');
    }

    public function comment(string $objectId, string $pageAccessToken, string $message): FacebookApiResult
    {
        $this->calls[] = ['method' => 'comment', 'object_id' => $objectId, 'message' => $message];

        return $this->commentResult ?? new FacebookApiResult(true, id: 'c1');
    }

    /** @return list<string> */
    public function methods(): array
    {
        return array_map(fn (array $call): string => (string) $call['method'], $this->calls);
    }
}
