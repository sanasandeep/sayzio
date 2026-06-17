import { PageLayout } from "@/components/layout/page-layout";
import { fetchBlogPost, formatPostDate } from "@/lib/blog-posts";
import { useQuery } from "@tanstack/react-query";
import { motion } from "framer-motion";
import { Link } from "wouter";
import { ArrowLeft } from "lucide-react";
import NotFound from "@/pages/not-found";

interface BlogPostProps {
  params: { slug: string };
}

export default function BlogPost({ params }: BlogPostProps) {
  const {
    data: post,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ["blog-post", params.slug],
    queryFn: ({ signal }) => fetchBlogPost(params.slug, signal),
  });

  if (isLoading) {
    return (
      <PageLayout title="Loading…" description="">
        <section className="py-20 lg:py-32">
          <div className="container mx-auto px-6">
            <div className="max-w-2xl mx-auto animate-pulse" aria-hidden="true">
              <div className="h-4 w-28 rounded bg-muted-foreground/10 mb-10" />
              <div className="h-5 w-40 rounded-full bg-muted-foreground/10 mb-6" />
              <div className="h-10 w-3/4 rounded bg-muted-foreground/10 mb-6" />
              <div className="h-4 w-32 rounded bg-muted-foreground/10 mb-10" />
              <div className="space-y-4">
                <div className="h-4 w-full rounded bg-muted-foreground/10" />
                <div className="h-4 w-full rounded bg-muted-foreground/10" />
                <div className="h-4 w-2/3 rounded bg-muted-foreground/10" />
              </div>
            </div>
          </div>
        </section>
      </PageLayout>
    );
  }

  if (isError) {
    return (
      <PageLayout title="Blog" description="">
        <section className="py-20 lg:py-32">
          <div className="container mx-auto px-6">
            <div className="max-w-2xl mx-auto text-center text-muted-foreground">
              <p className="mb-6">
                We couldn't load this post right now. Please try again later.
              </p>
              <Link
                href="/blog"
                className="inline-flex items-center text-sm font-semibold text-primary"
              >
                <ArrowLeft className="mr-2 w-4 h-4" />
                Back to blog
              </Link>
            </div>
          </div>
        </section>
      </PageLayout>
    );
  }

  if (!post) {
    return <NotFound />;
  }

  return (
    <PageLayout title={post.metaTitle ?? post.title} description={post.metaDescription ?? post.excerpt}>
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-2xl mx-auto">
            <Link
              href="/blog"
              className="inline-flex items-center text-sm font-semibold text-muted-foreground hover:text-primary transition-colors mb-10"
            >
              <ArrowLeft className="mr-2 w-4 h-4" />
              Back to blog
            </Link>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5 }}
            >
              <div className="flex flex-wrap items-center gap-3 mb-6 text-sm text-muted-foreground">
                <span className="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                  {post.category}
                </span>
                {post.date && <span>{formatPostDate(post.date)}</span>}
                <span aria-hidden="true">·</span>
                <span>{post.readingTime}</span>
              </div>

              <h1 className="text-3xl lg:text-5xl font-bold tracking-tight mb-6">
                {post.title}
              </h1>

              <p className="text-sm text-muted-foreground mb-10">
                By {post.author}
              </p>

              {post.coverImage && (
                <div className="mb-10 overflow-hidden rounded-3xl">
                  <img
                    src={post.coverImage}
                    alt={post.title}
                    className="w-full h-auto object-cover"
                  />
                </div>
              )}

              <article
                className="prose prose-lg dark:prose-invert max-w-none prose-headings:tracking-tight prose-a:text-primary"
                dangerouslySetInnerHTML={{ __html: post.bodyHtml ?? "" }}
              />
            </motion.div>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
