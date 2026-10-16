export type BlogPostSummary = {
    slug: string;
    title: string;
    excerpt: string;
    publishedAt: string;
    publishedAtLabel: string;
    heroImage: string | null;
};

export type BlogPostMeta = {
    slug: string;
    title: string;
    metaDescription: string;
    publishedAt: string;
    publishedAtLabel: string;
    heroImage: string | null;
    url: string;
};
