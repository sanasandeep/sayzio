<?php

namespace App\Modules\Common\Support;

use App\Modules\Common\Controllers\HomeController;

/**
 * The home page's short FAQ, as data rather than markup.
 *
 * ---- Why this class exists -------------------------------------------
 *
 * The questions used to live in an @php block inside the section partial,
 * which also built the FAQPage JSON-LD right next to them. That looked
 * tidy and was invisible where it mattered: the partial is part of the
 * DEFERRED fragment, fetched and injected after first paint. So the
 * structured data was generated correctly and then emitted into a
 * document a crawler's first pass never sees. The schema existed; it just
 * wasn't where it could do anything.
 *
 * Holding the copy here lets the visible <details> list and the head's
 * JSON-LD read from one source, so they cannot drift and the schema
 * always describes questions the page really shows.
 *
 * Each home design renders its own FAQ -- classic uses the set below, and
 * several of the short keyword designs carry their own. forActiveDesign()
 * returns a set only for a design whose questions are known here, so a
 * design with its own copy gets no head schema rather than classic's
 * questions wrongly attached to it. A design that moves its questions in
 * here starts getting head schema automatically.
 */
class HomeFaq
{
    /** The classic (full page) design's four questions. */
    public const CLASSIC = [
        ['q' => 'What is Sayzio?', 'a' => 'Sayzio connects your online presence with customer work: pages and links, enquiries, CRM, sales, projects and support, with optional AI tools along the way.'],
        ['q' => 'Can I use Sayzio with my existing workflow?', 'a' => 'Start with the tools you need, then connect your pages, contacts, plans and project resources as your workflow grows. Available integrations and limits depend on the feature and your plan.'],
        ['q' => 'Do I have to use AI?', 'a' => 'No. You can enter and edit content yourself. When you choose AI generation, review the coin estimate before you continue and review the result before using it.'],
        ['q' => 'What happens after a customer gets in touch?', 'a' => 'Keep the enquiry and customer details organized, manage the agreed sale, and connect the delivery work to a project. You choose the records and actions to connect; the example above illustrates the flow.'],
    ];

    /** Questions by home design key, for designs whose copy lives here. */
    private const BY_DESIGN = ['classic' => self::CLASSIC];

    /**
     * The questions the ACTIVE design actually renders, or [] when that
     * design keeps its own copy elsewhere. Empty is the safe answer:
     * MarketingSchema::faqPage([]) returns null and nothing is emitted.
     */
    public static function forActiveDesign(): array
    {
        return self::BY_DESIGN[HomeController::activeDesign()] ?? [];
    }
}
