<?php

use App\Support\Blog\BlogPosts;
use Inertia\Testing\AssertableInertia as Assert;

test('the blog index lists published posts publicly', function () {
    $posts = BlogPosts::all();
    $post = $posts->first();

    $this->get('/blog')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('blog/index')
            ->has('posts', $posts->count())
            ->where('posts.0.slug', $post->slug)
            ->where('posts.0.title', $post->title)
            ->where('posts.0.excerpt', $post->excerpt)
            ->has('posts.0.publishedAt')
        );
});

test('every published post renders its own page with SEO meta in props', function (string $slug) {
    $post = BlogPosts::find($slug);

    $this->get("/blog/{$slug}")
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component("blog/posts/{$slug}")
            ->where('post.slug', $post->slug)
            ->where('post.title', $post->title)
            ->where('post.metaDescription', $post->metaDescription)
            ->has('post.publishedAt')
        );
})->with(fn () => BlogPosts::all()->pluck('slug')->all());

test('an unknown blog slug 404s', function () {
    $this->get('/blog/does-not-exist')->assertNotFound();
});
