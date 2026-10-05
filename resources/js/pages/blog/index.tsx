import { Head, Link } from '@inertiajs/react';
import BlogHeader from '@/components/blog/blog-header';
import SiteFooter from '@/components/site-footer';
import { Card } from '@/components/ui/card';
import blog from '@/routes/blog';
import type { BlogPostSummary } from '@/types/blog';

type Props = {
    posts: BlogPostSummary[];
};

export default function BlogIndex({ posts }: Props) {
    return (
        <>
            <Head title="Blog — Kolvi">
                <meta
                    name="description"
                    content="Artículos de Kolvi sobre control de asistencia, cumplimiento laboral y automatización de RRHH en Chile."
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <BlogHeader />

                <section className="border-b bg-brand-navy-deep py-16">
                    <div className="mx-auto flex max-w-7xl flex-col gap-4 px-6">
                        <h1 className="text-4xl font-bold tracking-tight text-balance text-white sm:text-5xl">
                            Blog de Kolvi
                        </h1>
                        <p className="max-w-2xl text-lg leading-relaxed text-pretty text-white/70">
                            Guías y novedades sobre control de asistencia,
                            cumplimiento laboral y automatización de RRHH en
                            Chile.
                        </p>
                    </div>
                </section>

                <section className="py-16">
                    <div className="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-6 sm:grid-cols-2">
                        {posts.map((post) => (
                            <Link key={post.slug} href={blog.show(post.slug)}>
                                <Card className="h-full gap-3 p-6 transition-shadow hover:shadow-lg">
                                    <time
                                        dateTime={post.publishedAt}
                                        className="text-xs font-semibold tracking-wider text-brand-coral uppercase"
                                    >
                                        {post.publishedAtLabel}
                                    </time>
                                    <h2 className="text-xl font-semibold tracking-tight text-balance">
                                        {post.title}
                                    </h2>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {post.excerpt}
                                    </p>
                                </Card>
                            </Link>
                        ))}
                    </div>
                </section>

                <SiteFooter />
            </div>
        </>
    );
}
