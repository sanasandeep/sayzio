# Admin custom CSS and JavaScript

Open **Admin → Custom CSS & JS** (`/admin/site-custom-code`). Editing and saving require the existing `settings.manage` permission.

The two independent areas are:

- **Marketing pages:** homepage, features, pricing, blog, and other public marketing routes.
- **User dashboard:** authenticated documents under `/user/`, including settings and editors.

Both areas start disabled. Fill the relevant fields, enable that area, save, and reload a destination page. Turn an area off to pause execution without deleting its saved code. Public user-shared pages and the admin panel are excluded. Login, API, partial, attachment and download responses do not receive the code.

## Fields

- **Custom CSS:** raw CSS, no style tags; inserted at the end of the document head.
- **JavaScript in head:** raw JavaScript, no script tags; inserted in the head. Wait for `DOMContentLoaded` before accessing body elements.
- **JavaScript at body end:** raw JavaScript, no script tags; inserted before the closing body tag.
- **HTML snippets:** complete markup before closing head, immediately after opening body, or before closing body. These support vendor script/style tags, verification meta tags and noscript snippets.

The body-end HTML appears before the body-end JavaScript. Head HTML precedes the CSS and head JavaScript. Nothing in this editor executes on the admin page itself; saved content stays escaped in its textareas. Snippets execute as supplied on enabled destinations. Third-party analytics or marketing code must use the existing consent callbacks/API when consent is required; enabling a snippet is not itself visitor consent.

## Quick verification

For marketing CSS, save `body { --custom-code-check: marketing; }`. For marketing body JavaScript, save `window.sayzioCustomCodeCheck = 'marketing';`. Enable Marketing only, save, and inspect the homepage. The computed body custom property and window value should appear there and remain absent from `/user/dashboard`.

Repeat with Dashboard enabled and different values on an authenticated `/user/` page. Turn each area off and reload to verify removal. PHP feature tests cover persistence, permission rejection, validation, boundaries, removal and scope; the browser check verifies actual execution and applied styles.

# SEO and performance changes

- Organization schema uses the public branding icon instead of an expiring share-image URL.
- The homepage canonical drops non-content parameters; search and pagination on other pages keep their existing behavior.
- Shipped home SEO defaults describe business pages, CRM and AI marketing. The migration upgrades only exact older shipped copy and preserves custom copy.
- The production root startup shortcut is disabled by default. `/up` and `/api/healthz` remain the normal probes. Legacy autoscale deployments can explicitly enable `ROOT_STARTUP_PROBE`, restricted to recognized readiness clients during the boot window; curl, Python clients and Googlebot still receive normal homepage content.
- The homepage sitemap date combines saved content edits with a stable source revision. EC2 deploy runs `php artisan marketing:record-code-revision` before cache warming; it derives the timestamp from relevant Git source history, not the deployment/request clock.
- Shared stylesheet blocks are now content-hashed static files. Nginx serves `/css/marketing/` with immutable cache headers. Zio's body and the bundled small header icon use smaller WebP assets; custom uploaded branding remains unchanged.

Search Console indexing/canonical selection and real-user Core Web Vitals still require post-deployment verification. These code changes do not certify rankings or a PageSpeed score.
