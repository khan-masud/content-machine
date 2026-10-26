<?php

namespace Tests\Unit\Actions\Facebook;

use App\Actions\Facebook\SaveFacebookSettingsAction;
use App\Data\Facebook\FacebookApiResult;
use App\Data\Facebook\FacebookSettingsData;
use App\Models\Workspace;
use App\Support\Facebook\FacebookConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\Facebook\FakeFacebookPageClient;
use Tests\TestCase;

class SaveFacebookSettingsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_verifies_the_page_and_stores_an_encrypted_token(): void
    {
        $workspace = Workspace::factory()->create();
        $client = new FakeFacebookPageClient;

        (new SaveFacebookSettingsAction($client))->handle($workspace, new FacebookSettingsData('1234567890', 'tok', true, 'bn'));

        $config = FacebookConfig::fromWorkspace($workspace->fresh());
        $this->assertSame('Amar Page', $config->pageName());
        $this->assertSame('tok', $config->pageAccessToken());
        $this->assertTrue($config->isReady());
        $this->assertNotSame('tok', $workspace->fresh()?->settings['facebook']['page_access_token'] ?? null);
        $this->assertSame(['method' => 'page', 'page_id' => '1234567890', 'token' => 'tok'], $client->calls[0]);
    }

    public function test_an_omitted_token_reuses_the_saved_one(): void
    {
        $workspace = Workspace::factory()->create();
        FakeFacebookPageClient::connect($workspace);
        $client = new FakeFacebookPageClient;

        (new SaveFacebookSettingsAction($client))->handle($workspace, new FacebookSettingsData(FakeFacebookPageClient::PAGE_ID, null, false, 'en'));

        $config = FacebookConfig::fromWorkspace($workspace->fresh());
        $this->assertSame('page-token', $config->pageAccessToken());
        $this->assertFalse($config->publishEnabled());
        $this->assertSame('en', $config->captionLanguage());
    }

    public function test_a_rejected_token_or_a_token_for_another_page_is_not_saved(): void
    {
        $workspace = Workspace::factory()->create();
        $client = new FakeFacebookPageClient;
        $client->pageResult = FacebookApiResult::failure('Invalid OAuth access token.');

        try {
            (new SaveFacebookSettingsAction($client))->handle($workspace, new FacebookSettingsData('1234567890', 'bad', true, 'bn'));
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Invalid OAuth', $exception->errors()['page_access_token'][0]);
        }

        $client->pageResult = new FacebookApiResult(true, id: '999999', name: 'Other');
        $this->expectException(ValidationException::class);
        (new SaveFacebookSettingsAction($client))->handle($workspace, new FacebookSettingsData('1234567890', 'other', true, 'bn'));
    }
}
