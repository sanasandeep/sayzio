import { PageLayout } from "@/components/layout/page-layout";
import { blogPosts, formatPostDate } from "@/lib/blog-posts";
import { motion } from "framer-motion";
import { Link } from "wouter";
import { ArrowRight } from "lucide-react";

export default function Blog() {
  return (
    <PageLayout
      title="Blog"
      description="Stories, product thinking, and tips from the 1INME team on biolinks, analytics, and growing your audience."
    >
      <section className="py-20 lg:py-32">
        <div className="container mx-auto px-6">
          <div className="max-w-3xl mx-auto text-center mb-16">
            <p className="text-sm font-semibold uppercase tracking-widest text-primary mb-4">
              Blog
            </p>
            <h1 className="text-4xl lg:text-6xl font-bold tracking-tight mb-6">
              Notes from{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-primary to-accent-foreground">
                the workshop.
              </span>
            </h1>
            <p className="text-xl text-muted-foreground">
              Product thinking, tips, and the occasional opinion on building one
              link that does everything.
            </p>
          </div>

          <div className="max-w-3xl mx-auto space-y-8">
            {blogPosts.map((post, index) => (
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
                  <div className="flex flex-wrap items-center gap-3 mb-4 text-sm text-muted-foreground">
                    <span className="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                      {post.category}
                    </span>
                    <span>{formatPostDate(post.date)}</span>
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
