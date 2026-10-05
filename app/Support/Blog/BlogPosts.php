<?php

namespace App\Support\Blog;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The blog's post catalog. Adding a post means adding an entry here and its
 * matching `resources/js/pages/blog/posts/{slug}.tsx` page — see
 * {@see BlogPost}.
 */
final class BlogPosts
{
    /** @return Collection<int, BlogPost> newest post first */
    public static function all(): Collection
    {
        return collect([
            new BlogPost(
                slug: 'beneficios-de-conectar-un-mcp-a-tu-sistema-de-rrhh',
                title: 'Beneficios de conectar un MCP a tu sistema de RRHH',
                excerpt: 'Qué cambia cuando un asistente de IA puede consultar licencias, horas extra y nómina directamente en tu sistema de RRHH, sin planillas intermedias.',
                metaDescription: 'Descubre los beneficios de conectar un servidor MCP a tu sistema de RRHH: consulta licencias, horas extra y nómina desde un asistente de IA, sin planillas ni exportaciones manuales.',
                publishedAt: Carbon::parse('2026-10-05'),
            ),
        ])->sortByDesc(fn (BlogPost $post) => $post->publishedAt)->values();
    }

    public static function find(string $slug): ?BlogPost
    {
        return self::all()->first(fn (BlogPost $post) => $post->slug === $slug);
    }
}
