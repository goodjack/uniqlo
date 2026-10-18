<?php

namespace Tests\Feature;

use App\Models\SocialPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialPostImageTest extends TestCase
{
    use RefreshDatabase;

    private function seedPost(array $attributes = []): SocialPost
    {
        return SocialPost::create(array_merge([
            'notion_page_id' => 'page-1',
            'notion_created_at' => now(),
            'notion_edited_at' => now(),
            'source_url' => 'https://www.threads.com/@a/post/1',
            'canonical_url' => 'https://www.threads.com/@a/post/1',
            'platform' => 'threads',
            'kind' => '貼文',
            'cover_url' => 'https://scontent.cdninstagram.com/p.jpg',
            'status' => 'ok',
        ], $attributes));
    }

    public function test_image_is_relayed_with_long_cache_headers_and_not_stored(): void
    {
        Http::fake(['scontent.cdninstagram.com/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])]);
        $post = $this->seedPost();

        $response = $this->get(route('social-posts.image', ['socialPost' => $post->id]));

        $response->assertOk();
        $this->assertSame('jpeg-bytes', $response->getContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=604800', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    public function test_expired_or_non_image_upstream_becomes_a_short_lived_404(): void
    {
        Http::fake(['scontent.cdninstagram.com/*' => Http::response('<html>forbidden</html>', 403, ['Content-Type' => 'text/html'])]);
        $post = $this->seedPost();

        $response = $this->get(route('social-posts.image', ['socialPost' => $post->id]));

        $response->assertNotFound();
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
    }

    public function test_svg_is_not_relayed_because_it_can_carry_scripts(): void
    {
        Http::fake(['scontent.cdninstagram.com/*' => Http::response('<svg onload="alert(1)"/>', 200, ['Content-Type' => 'image/svg+xml'])]);
        $post = $this->seedPost();

        $this->get(route('social-posts.image', ['socialPost' => $post->id]))->assertNotFound();
    }

    public function test_only_known_cdn_hosts_and_visible_posts_are_relayed(): void
    {
        Http::fake();
        $foreign = $this->seedPost(['cover_url' => 'https://evil.example.com/a.jpg']);
        $failed = $this->seedPost(['notion_page_id' => 'page-2', 'status' => 'failed']);

        $this->get(route('social-posts.image', ['socialPost' => $foreign->id]))->assertNotFound();
        $this->get(route('social-posts.image', ['socialPost' => $failed->id]))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_x_images_are_relayed_only_from_pbs_twimg(): void
    {
        Http::fake(['pbs.twimg.com/*' => Http::response('webp-bytes', 200, ['Content-Type' => 'image/webp'])]);
        $x = $this->seedPost([
            'platform' => 'x',
            'canonical_url' => 'https://x.com/a/status/1',
            'cover_url' => 'https://pbs.twimg.com/amplify_video_thumb/1/img/a?format=webp&name=large',
        ]);
        $logo = $this->seedPost(['notion_page_id' => 'page-2', 'platform' => 'x', 'cover_url' => 'https://abs.twimg.com/x-web/assets/og/default-og.1.jpg']);
        $lookalike = $this->seedPost(['notion_page_id' => 'page-3', 'platform' => 'x', 'cover_url' => 'https://pbs.twimg.com.evil.example.com/a.jpg']);

        $response = $this->get(route('social-posts.image', ['socialPost' => $x->id]));
        $response->assertOk();
        $this->assertSame('image/webp', $response->headers->get('Content-Type'));

        $this->get(route('social-posts.image', ['socialPost' => $logo->id]))->assertNotFound();
        $this->get(route('social-posts.image', ['socialPost' => $lookalike->id]))->assertNotFound();
        Http::assertSentCount(1);
    }
}
