<?php

namespace App\Http\Controllers;

use App\Support\Blog\BlogPosts;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public marketing blog (KOL-145). Unauthenticated, SEO-targeted content
 * reachable from the landing page.
 */
class BlogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('blog/index', [
            'posts' => BlogPosts::all()
                ->map(fn ($post) => $post->toSummaryArray())
                ->all(),
        ]);
    }

    public function show(string $slug): Response
    {
        $post = BlogPosts::find($slug);

        abort_if($post === null, 404);

        return Inertia::render("blog/posts/{$post->slug}", [
            'post' => [
                ...$post->toMetaArray(),
                'url' => route('blog.show', $post->slug),
            ],
        ]);
    }
}
