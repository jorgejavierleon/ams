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
            new BlogPost(
                slug: 'resolucion-38-direccion-del-trabajo-como-cumplir',
                title: 'Resolución 38 de la Dirección del Trabajo: qué exige y cómo cumplirla',
                excerpt: 'Qué obliga la Resolución 38 a los sistemas electrónicos de control de asistencia —hash en cada marca, reportes para la DT, vencimiento de días de descanso— y cómo cumplirla en la práctica.',
                metaDescription: 'Guía de cumplimiento de la Resolución 38 de la Dirección del Trabajo: a qué sistemas aplica, qué exige en cada marcación, qué reportes puede pedir la DT y cómo evitar sanciones.',
                publishedAt: Carbon::parse('2026-10-15'),
            ),
            new BlogPost(
                slug: 'como-calcular-horas-extras-en-chile-sin-infringir-los-limites-legales',
                title: 'Cómo calcular las horas extras en Chile sin infringir los límites legales',
                excerpt: 'El tope diario y semanal de horas extraordinarias, qué es el pacto de horas extras y por qué caduca a los tres meses, y cómo evitar que un error de cálculo se convierta en una multa.',
                metaDescription: 'Guía práctica para calcular las horas extras en Chile: tope legal de 2 horas diarias, el pacto de horas extraordinarias y su vigencia de 3 meses, el recargo del 50% y los riesgos de calcularlas mal.',
                publishedAt: Carbon::parse('2026-10-15'),
            ),
            new BlogPost(
                slug: 'que-exige-la-ley-40-horas-sobre-el-control-de-asistencia',
                title: 'Qué exige la Ley 40 horas sobre el control de asistencia: libro, reloj control o sistema electrónico',
                excerpt: 'El Código del Trabajo obliga a controlar la asistencia con uno de tres mecanismos. Qué exige cada uno, por qué el libro de papel es el más débil en la práctica y qué pasó con esa obligación el 26 de abril de 2026.',
                metaDescription: 'Control de asistencia obligatorio en Chile: qué exige la Ley 40 horas, las diferencias entre libro de asistencia, reloj control y sistema electrónico de registro, y por qué el papel es el más riesgoso de los tres.',
                publishedAt: Carbon::parse('2026-10-16'),
                heroImage: '/images/blog/ley-40-horas-reloj-control.webp',
            ),
        ])->sortByDesc(fn (BlogPost $post) => $post->publishedAt)->values();
    }

    public static function find(string $slug): ?BlogPost
    {
        return self::all()->first(fn (BlogPost $post) => $post->slug === $slug);
    }
}
