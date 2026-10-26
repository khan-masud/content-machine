<?php

namespace Tests\Unit\Support\PhotoCards\Backgrounds;

use App\Contracts\PexelsProvider;
use App\Contracts\PexelsProviderFactory;
use App\Data\Pexels\PexelsPhotoData;
use App\Models\Post;
use App\Models\Workspace;
use App\Support\PhotoCards\Backgrounds\PexelsCardBackgroundSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PhotoCards\FakePhotoCardRenderer;
use Tests\TestCase;

class PexelsCardBackgroundSourceTest extends TestCase
{
    use RefreshDatabase;

    private function factory(?PexelsProvider $provider): PexelsProviderFactory
    {
        return new class($provider) implements PexelsProviderFactory
        {
            public function __construct(private readonly ?PexelsProvider $provider) {}

            public function forWorkspace(Workspace $workspace): ?PexelsProvider
            {
                return $this->provider;
            }
        };
    }

    private function provider(): PexelsProvider
    {
        return new class implements PexelsProvider
        {
            public ?string $query = null;

            public function search(string $query, string $orientation, int $perPage): array
            {
                $this->query = $query;

                return array_map(fn (int $id): PexelsPhotoData => new PexelsPhotoData(
                    $id, 'Photographer '.$id, 'https://pexels.com/@p', 'https://pexels.com/photo/'.$id, 'https://images.pexels.com/p.jpg', 'https://images.pexels.com/'.$id.'.jpg',
                ), [11, 22]);
            }

            public function photo(int $id): PexelsPhotoData
            {
                throw new \LogicException('not used');
            }

            public function download(PexelsPhotoData $photo): array
            {
                return ['bytes' => FakePhotoCardRenderer::png(), 'mime' => 'image/png'];
            }
        };
    }

    public function test_it_picks_a_search_result_by_variation_and_credits_the_photographer(): void
    {
        $post = Post::factory()->create(['photo_card' => ['image_query' => 'power outage city']]);
        $provider = $this->provider();
        $source = new PexelsCardBackgroundSource($this->factory($provider));

        $first = $source->find($post, 0);
        $second = $source->find($post, 1);

        $this->assertSame('power outage city', $provider->query);
        $this->assertSame(11, $first?->meta['pexels_id']);
        $this->assertSame('Photographer 22', $second?->meta['photographer']);
        $this->assertSame('pexels', $second->source);
    }

    public function test_without_a_pexels_key_there_is_nothing(): void
    {
        $post = Post::factory()->create();

        $this->assertNull((new PexelsCardBackgroundSource($this->factory(null)))->find($post));
    }
}
