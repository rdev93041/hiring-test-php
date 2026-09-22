<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Response;

class TagController extends Controller
{
    public function index()
    {
        return TagResource::collection(Tag::query()->orderBy('id')->paginate(15));
    }

    public function store(StoreTagRequest $request)
    {
        $tag = Tag::query()->create($request->validated());

        return (new TagResource($tag))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Tag $tag)
    {
        return new TagResource($tag);
    }

    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $tag->update($request->validated());

        return new TagResource($tag);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();

        return response()->noContent();
    }
}
