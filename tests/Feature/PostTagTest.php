<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTagTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->post = Post::factory()->for($user)->create();
    }

    public function test_can_attach_multiple_tags_to_post(): void
    {
        $firstTag = $this->createTag('first');
        $secondTag = $this->createTag('second');

        $response = $this->postJson("/api/posts/{$this->post->id}/tags", [
            'tag_ids' => [$firstTag->id, $secondTag->id],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $firstTag->id,
        ]);
        $this->assertDatabaseHas('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $secondTag->id,
        ]);
    }

    public function test_can_detach_tag_from_post(): void
    {
        $detachedTag = $this->createTag('detached');
        $remainingTag = $this->createTag('remaining');
        $this->post->tags()->attach([$detachedTag->id, $remainingTag->id]);

        $response = $this->deleteJson(
            "/api/posts/{$this->post->id}/tags/{$detachedTag->id}"
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $detachedTag->id,
        ]);
        $this->assertDatabaseHas('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $remainingTag->id,
        ]);
    }

    public function test_show_post_returns_attached_tags(): void
    {
        $firstTag = $this->createTag('first');
        $secondTag = $this->createTag('second');
        $unattachedTag = $this->createTag('unattached');
        $this->post->tags()->attach([$firstTag->id, $secondTag->id]);

        $response = $this->getJson("/api/posts/{$this->post->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data.tags');
        $response->assertJsonFragment([
            'id' => $firstTag->id,
            'name' => $firstTag->name,
            'slug' => $firstTag->slug,
        ]);
        $response->assertJsonFragment([
            'id' => $secondTag->id,
            'name' => $secondTag->name,
            'slug' => $secondTag->slug,
        ]);
        $response->assertJsonMissing([
            'id' => $unattachedTag->id,
            'name' => $unattachedTag->name,
            'slug' => $unattachedTag->slug,
        ]);
    }

    public function test_attach_rejects_nonexistent_tag_id(): void
    {
        $response = $this->postJson("/api/posts/{$this->post->id}/tags", [
            'tag_ids' => [999999],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tag_ids.0']);
        $this->assertDatabaseCount('post_tag', 0);
    }

    public function test_attach_rejects_duplicate_tag_ids(): void
    {
        $tag = $this->createTag('duplicate');

        $response = $this->postJson("/api/posts/{$this->post->id}/tags", [
            'tag_ids' => [$tag->id, $tag->id],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['tag_ids.0', 'tag_ids.1']);
        $this->assertDatabaseCount('post_tag', 0);
    }

    public function test_repeated_attach_does_not_create_duplicate(): void
    {
        $tag = $this->createTag('repeated');
        $payload = ['tag_ids' => [$tag->id]];

        $this->postJson("/api/posts/{$this->post->id}/tags", $payload)
            ->assertNoContent();
        $this->postJson("/api/posts/{$this->post->id}/tags", $payload)
            ->assertNoContent();

        $this->assertDatabaseCount('post_tag', 1);
    }

    public function test_attaching_new_tag_keeps_existing_relations(): void
    {
        $existingTag = $this->createTag('existing');
        $newTag = $this->createTag('new');
        $this->post->tags()->attach($existingTag);

        $response = $this->postJson("/api/posts/{$this->post->id}/tags", [
            'tag_ids' => [$newTag->id],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $existingTag->id,
        ]);
        $this->assertDatabaseHas('post_tag', [
            'post_id' => $this->post->id,
            'tag_id' => $newTag->id,
        ]);
        $this->assertDatabaseCount('post_tag', 2);
    }

    private function createTag(string $suffix): Tag
    {
        return Tag::query()->create([
            'name' => "Tag {$suffix}",
            'slug' => "tag-{$suffix}",
        ]);
    }
}
