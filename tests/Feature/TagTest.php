<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_tags_with_pagination(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->createTag("tag-{$i}");
        }

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('data.0.slug', 'tag-1');

        $this->getJson('/api/tags?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'tag-16');
    }

    public function test_can_create_tag_using_only_validated_fields(): void
    {
        $response = $this->postJson('/api/tags', [
            'name' => 'Laravel',
            'slug' => 'laravel',
            'id' => 999999,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Laravel')
            ->assertJsonPath('data.slug', 'laravel');
        $this->assertNotEquals(999999, $response->json('data.id'));
        $this->assertDatabaseHas('tags', [
            'id' => $response->json('data.id'),
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
    }

    public function test_can_show_tag(): void
    {
        $tag = $this->createTag('laravel');

        $this->getJson("/api/tags/{$tag->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ]]);
    }

    public function test_can_update_tag_with_put(): void
    {
        $tag = $this->createTag('old');

        $this->putJson("/api/tags/{$tag->id}", [
            'name' => 'Updated',
            'slug' => 'updated',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated')
            ->assertJsonPath('data.slug', 'updated');

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Updated',
            'slug' => 'updated',
        ]);
    }

    public function test_patch_preserves_omitted_fields_and_accepts_own_slug(): void
    {
        $tag = $this->createTag('laravel');

        $this->patchJson("/api/tags/{$tag->id}", ['name' => 'New name'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'laravel');

        $this->patchJson("/api/tags/{$tag->id}", ['slug' => 'laravel'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New name');

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'New name',
            'slug' => 'laravel',
        ]);
    }

    public function test_delete_removes_pivot_links_and_preserves_posts_and_other_tags(): void
    {
        $tag = $this->createTag('deleted');
        $remaining = $this->createTag('remaining');
        $posts = Post::factory()->count(2)->create();
        foreach ($posts as $post) {
            $post->tags()->attach([$tag->id, $remaining->id]);
        }

        $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();

        $this->assertModelMissing($tag);
        $this->assertModelExists($remaining);
        $this->assertDatabaseMissing('post_tag', ['tag_id' => $tag->id]);
        foreach ($posts as $post) {
            $this->assertModelExists($post);
            $this->assertDatabaseHas('post_tag', [
                'post_id' => $post->id,
                'tag_id' => $remaining->id,
            ]);
        }
    }

    public function test_create_requires_name_and_slug(): void
    {
        $this->postJson('/api/tags', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug']);

        $this->assertDatabaseCount('tags', 0);
    }

    #[DataProvider('invalidFields')]
    public function test_create_and_update_reject_invalid_fields(string $field, mixed $value): void
    {
        $tag = $this->createTag('original');
        $payload = array_replace(['name' => 'Valid', 'slug' => 'valid'], [$field => $value]);

        $this->postJson('/api/tags', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
        $this->patchJson("/api/tags/{$tag->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('tags', 1);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => 'original',
        ]);
    }

    public static function invalidFields(): array
    {
        return [
            'empty name' => ['name', ''],
            'null slug' => ['slug', null],
            'non-string name' => ['name', ['invalid']],
            'non-string slug' => ['slug', 123],
            'long name' => ['name', str_repeat('n', 256)],
            'long slug' => ['slug', str_repeat('s', 256)],
        ];
    }

    public function test_create_and_update_reject_another_tags_slug(): void
    {
        $this->createTag('taken');
        $tag = $this->createTag('original');

        $this->postJson('/api/tags', ['name' => 'New', 'slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
        $this->patchJson("/api/tags/{$tag->id}", ['slug' => 'taken'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);

        $this->assertDatabaseCount('tags', 2);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'slug' => 'original']);
    }

    public function test_unknown_tag_returns_not_found(): void
    {
        $this->getJson('/api/tags/999999')->assertNotFound();
        $this->putJson('/api/tags/999999', ['name' => 'Name', 'slug' => 'slug'])
            ->assertNotFound();
        $this->patchJson('/api/tags/999999', ['name' => 'Name'])->assertNotFound();
        $this->deleteJson('/api/tags/999999')->assertNotFound();
    }

    private function createTag(string $slug): Tag
    {
        return Tag::query()->create(['name' => "Tag {$slug}", 'slug' => $slug]);
    }
}
