import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import BlogHeader from '@/components/blog/blog-header';
import SiteFooter from '@/components/site-footer';
import type { BlogPostMeta } from '@/types/blog';

type Props = {
    post: BlogPostMeta;
    children: ReactNode;
};

/**
 * Shared chrome for a blog post page: SEO/OG meta tags, header, article
 * typography (hand-styled via child selectors — the app has no Tailwind
 * typography plugin) and footer. Each post's own page supplies the body as
 * `children`.
 */
export default function BlogPostLayout({ post, children }: Props) {
    return (
        <>
            <Head title={`${post.title} — Blog Kolvi`}>
                <meta name="description" content={post.metaDescription} />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={post.title} />
                <meta
                    property="og:description"
                    content={post.metaDescription}
                />
                <meta property="og:url" content={post.url} />
                <meta
                    property="article:published_time"
                    content={post.publishedAt}
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <BlogHeader />

                <article className="py-16">
                    <div className="mx-auto flex max-w-3xl flex-col gap-6 px-6">
                        <time
                            dateTime={post.publishedAt}
                            className="text-xs font-semibold tracking-wider text-brand-coral uppercase"
                        >
                            {post.publishedAtLabel}
                        </time>
                        <h1 className="text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                            {post.title}
                        </h1>
                        <div className="flex flex-col gap-5 text-base leading-relaxed text-pretty [&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2 [&_h2]:mt-4 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-foreground [&_li]:text-muted-foreground [&_p]:text-muted-foreground [&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-2 [&_ul]:pl-6">
                            {children}
                        </div>
                    </div>
                </article>

                <SiteFooter />
            </div>
        </>
    );
}
