<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table) {
                $table->id();
                $table->text('quote');
                $table->string('author_name', 120);
                $table->string('author_role', 160)->nullable();
                $table->string('accent_color', 16)->default('#7c3aed');
                $table->unsignedTinyInteger('rating')->default(5);
                $table->enum('row', ['top', 'bottom'])->default('top');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'row', 'sort_order']);
            });
        }

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }

    private function seedDefaults(): void
    {
        $now = now();

        // 10 testimonials for the top marquee row
        $top = [
            ['Sayzio made it stupidly easy to put my podcast, shop and templates on one page.', 'Jane Doe', 'Creator', '#1bd4d9'],
            ['The QR codes paid for the plan in a week — I changed the destination 3 times without reprinting.', 'Marco P.', 'Café owner', '#e94e8c'],
            ['Finally I can see where my audience actually lives. Game changer.', 'Aisha K.', 'Travel writer', '#ffc845'],
            ['The Performance Coach is like having a growth marketer on speed-dial.', 'Devon S.', 'Indie founder', '#7c3aed'],
            ['Set up my whole agency contact page in 10 minutes.', 'Priya N.', 'Agency lead', '#ff8a3c'],
            ['Switched from Linktree on a Friday, doubled my click-through rate by Monday.', 'Lina O.', 'Beauty creator', '#1bd4d9'],
            ['NFC tags on every business card — I just tap and people land on my page. Magic.', 'Hugo R.', 'Real-estate agent', '#7c3aed'],
            ['Built-in DMs mean I never lose a brand deal in my Instagram inbox again.', 'Maya D.', 'Fashion creator', '#e94e8c'],
            ['The drag-and-drop editor genuinely feels like Notion meets Linktree. Love it.', 'Tom S.', 'Product designer', '#ffc845'],
            ['Our podcast jumped 3 spots in the charts after the new biolink. Not even kidding.', 'Sam W.', 'Podcast host', '#ff8a3c'],
        ];

        // 25 testimonials for the bottom marquee row
        $bottom = [
            ['Forms + auto-responder replaced our entire MailerLite setup.', 'Elena V.', 'Coach', '#1bd4d9'],
            ['Live geo heatmap showed us our biggest market is Berlin. Who knew?', 'Pablo M.', 'Music producer', '#7c3aed'],
            ['Branded short links just look more trustworthy. Conversions up 18%.', 'Kira J.', 'Affiliate marketer', '#e94e8c'],
            ['The free plan is honestly more than enough for half our team.', 'Noah T.', 'Indie hacker', '#ffc845'],
            ['Pixel + UTM management baked in — no more spreadsheets.', 'Yuki H.', 'Performance marketer', '#ff8a3c'],
            ['I built my entire merch store as a biolink page. No Shopify needed.', 'Zoe B.', 'Streetwear designer', '#7c3aed'],
            ['The mobile app lets me ship a campaign from the beach. 10/10.', 'Ravi K.', 'Travel vlogger', '#1bd4d9'],
            ['Polls inside my biolink doubled comments on my last drop.', 'Mia C.', 'Music artist', '#e94e8c'],
            ['Their RSVP block is the cleanest I’ve seen — I use it for every event.', 'Chris O.', 'Community lead', '#ffc845'],
            ['AI Voice Assistant answers fan questions for me at 3am. Surreal.', 'Ana L.', 'Fitness creator', '#ff8a3c'],
            ['Dark theme + custom fonts make it feel like *my* site, not a template.', 'Jordan F.', 'Photographer', '#7c3aed'],
            ['Reordering blocks by drag-and-drop is faster than Squarespace, by far.', 'Selma G.', 'Wedding planner', '#1bd4d9'],
            ['Coach told me which CTA to swap and I closed two sponsorships that week.', 'Theo R.', 'Cycling creator', '#e94e8c'],
            ['Built-in analytics + revenue in one dashboard means no more tab juggling.', 'Olivia P.', 'Newsletter writer', '#ffc845'],
            ['Their tip jar takes Stripe AND PayPal. Took me 2 minutes to set up.', 'Ben H.', 'Indie musician', '#ff8a3c'],
            ['I run my agency’s lead-gen entirely from one Sayzio page.', 'Carla S.', 'Agency owner', '#7c3aed'],
            ['File sales (PDFs + photo packs) just… work. No Gumroad fees.', 'Sasha I.', 'Photographer', '#1bd4d9'],
            ['Their NFC dynamic redirect is the killer feature for restaurants.', 'Gio P.', 'Restaurateur', '#e94e8c'],
            ['Switched our whole studio link strategy in one afternoon.', 'Rae M.', 'Studio director', '#ffc845'],
            ['Dynamic QR menus saved us €600 a year in reprinting.', 'Lukas A.', 'Bar owner', '#ff8a3c'],
            ['The follower system means I email superfans without paying Mailchimp.', 'Fatima E.', 'Author', '#7c3aed'],
            ['Templates got me live in 12 minutes. Wild.', 'Kai N.', 'Side-project builder', '#1bd4d9'],
            ['My event check-in flow runs entirely on their RSVP + ticket blocks.', 'Hana B.', 'Event organiser', '#e94e8c'],
            ['Multi-workspace + team roles means I can manage 6 client pages cleanly.', 'Daniela U.', 'Freelance manager', '#ffc845'],
            ['The analytics export is finally a CSV my client actually opens.', 'Ivo P.', 'Web consultant', '#ff8a3c'],
        ];

        $rows = [];
        foreach ($top as $i => $t) {
            $rows[] = [
                'quote' => $t[0],
                'author_name' => $t[1],
                'author_role' => $t[2],
                'accent_color' => $t[3],
                'rating' => 5,
                'row' => 'top',
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach ($bottom as $i => $t) {
            $rows[] = [
                'quote' => $t[0],
                'author_name' => $t[1],
                'author_role' => $t[2],
                'accent_color' => $t[3],
                'rating' => 5,
                'row' => 'bottom',
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('testimonials')->insert($rows);
    }
};
