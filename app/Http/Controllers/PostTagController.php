<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachTagsRequest;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Response;

class PostTagController extends Controller
{
    public function store(AttachTagsRequest $request, Post $post): Response
    {
        $post->tags()->syncWithoutDetaching($request->validated('tag_ids'));

        return response()->noContent();
    }

    public function destroy(Post $post, Tag $tag): Response
    {
        $post->tags()->detach($tag);

        return response()->noContent();
    }
}
