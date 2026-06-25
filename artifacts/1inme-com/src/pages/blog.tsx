import { PageLayout } from "@/components/layout/page-layout";
import { fetchBlogPosts, formatPostDate } from "@/lib/blog-posts";
import { useQuery } from "@tanstack/react-query";
import { motion } from "framer-motion";
import { Link } from "wouter";
import { ArrowRight } from "lucide-react";

export default function Blog() {
  const {
    data: posts,
    isLoading,
    isError,
  } = useQuery({
    queryKey: ["blog-posts"],
    queryFn: ({ signal }) => fetchBlogPosts(signal),
  });

  return (
    <PageLayout
      title="Blog"
      description="Stories, product thinking, and tips from the Sayzio team on Link in Bio pages, analytics, and growing your audience."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              From the blog
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Stories and{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                insights.
              </span>
            </h1>
            <p className="text-xl text-muted-foreground">
              Tips, product news and creator deep-dives — fresh from the Sayzio
              team.
            </p>
          </div>

          <div className="max-w-3xl mx-auto space-y-8">
            {isLoading &&
              Array.from({ length: 3 }).map((_, index) => (
                <div
                  key={index}
                  className="glass-card p-8 rounded-3xl animate-pulse"
                  aria-hidden="true"
                >
                  <div className="h-5 w-32 rounded-full bg-muted-foreground/10 mb-4" />
                  <div className="h-7 w-3/4 rounded bg-muted-foreground/10 mb-3" />
                  <div className="h-4 w-full rounded bg-muted-foreground/10 mb-2" />
                  <div className="h-4 w-2/3 rounded bg-muted-foreground/10" />
                </div>
              ))}

            {isError && (
              <div className="glass-card p-8 rounded-3xl text-center text-muted-foreground">
                We couldn't load the blog right now. Please try again later.
              </div>
            )}

            {!isLoading && !isError && posts && posts.length === 0 && (
              <div className="glass-card p-8 rounded-3xl text-center text-muted-foreground">
                No posts yet — check back soon.
              </div>
            )}

            {!isLoading &&
              !isError &&
              posts &&
              posts.map((post, index) => (
                <motion.article
                  key={post.slug}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true, margin: "-80px" }}
                  transition={{ duration: 0.5, delay: index * 0.05 }}
                >
                  <Link
                    href={`/blog/${post.slug}`}
                    className="block glass-card p-8 rounded-3xl group transition-transform hover:-translate-y-1"
                  >
                    {post.coverImage && (
                      <div className="mb-6 -mx-2 overflow-hidden rounded-2xl">
                        <img
                          src={post.coverImage}
                          alt=""
                          loading="lazy"
                          className="w-full h-auto object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                      </div>
                    )}
                    <div className="flex flex-wrap items-center gap-3 mb-4 text-sm text-muted-foreground">
                      <span className="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                        {post.category}
                      </span>
                      {post.date && <span>{formatPostDate(post.date)}</span>}
                      <span aria-hidden="true">·</span>
                      <span>{post.readingTime}</span>
                    </div>
                    <h2 className="text-2xl font-semibold mb-3 group-hover:text-primary transition-colors">
                      {post.title}
                    </h2>
                    <p className="text-muted-foreground leading-relaxed mb-5">
                      {post.excerpt}
                    </p>
                    <span className="inline-flex items-center text-sm font-semibold text-primary">
                      Read more
                      <ArrowRight className="ml-2 w-4 h-4 transition-transform group-hover:translate-x-1" />
                    </span>
                  </Link>
                </motion.article>
              ))}
          </div>
        </div>
      </section>
    </PageLayout>
  );
}
