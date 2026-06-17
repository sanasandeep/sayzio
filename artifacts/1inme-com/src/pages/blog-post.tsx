import { PageLayout } from "@/components/layout/page-layout";
import { getPostBySlug, formatPostDate } from "@/lib/blog-posts";
import { motion } from "framer-motion";
import { Link } from "wouter";
import { ArrowLeft } from "lucide-react";
import NotFound from "@/pages/not-found";

interface BlogPostProps {
  params: { slug: string };
}

export default function BlogPost({ params }: BlogPostProps) {
  const post = getPostBySlug(params.slug);

  if (!post) {
    return <NotFound />;
  }

  return (
    <PageLayout title={post.title} description={post.excerpt}>
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
                <span>{formatPostDate(post.date)}</span>
                <span aria-hidden="true">·</span>
                <span>{post.readingTime}</span>
              </div>

              <h1 className="text-3xl lg:text-5xl font-bold tracking-tight mb-6">
                {post.title}
              </h1>

              <p className="text-sm text-muted-foreground mb-10">
                By {post.author}
              </p>

              <article className="space-y-6">
                {post.content.map((paragraph, index) => (
                  <p
                    key={index}
                    className="text-lg text-muted-foreground leading-relaxed"
                  >
                    {paragraph}
                  </p>
                ))}
              </article>
            </motion.div>
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
