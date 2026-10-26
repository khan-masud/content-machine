<?php

namespace App\Contracts;

use App\Data\Facebook\FacebookApiResult;

interface FacebookPageClient
{
    /** Reads the Page (id, name) with its token; used to verify a connection. */
    public function page(string $pageId, string $pageAccessToken): FacebookApiResult;

    /** Publishes a photo post; `postId` is the feed post id. */
    public function publishPhoto(string $pageId, string $pageAccessToken, string $photoContents, string $filename, string $caption): FacebookApiResult;

    /** Publishes a text-only post. */
    public function publishText(string $pageId, string $pageAccessToken, string $message): FacebookApiResult;

    public function comment(string $objectId, string $pageAccessToken, string $message): FacebookApiResult;
}
