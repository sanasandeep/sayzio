<?php

namespace App\Modules\Common\Support;

use Illuminate\Support\Str;

/**
 * "How you would use it", one line per link type, for the expanded card on
 * the marketing home page.
 *
 * Drafted copy. Each line describes the occasion for a type rather than
 * making any claim about results, and lookup is by slug so an admin who
 * renames a type in the SitePage list gets no line rather than the wrong
 * one. When these settle they belong beside name and desc in that same
 * admin-editable list; this class is where they live until then.
 */
class LinkTypeUsage
{
    private const LINES = [
        'short-link'        => 'Print it once on a poster or a pack, then change where it goes whenever the campaign does.',
        'link-in-bio'       => 'The one address in your Instagram or TikTok bio, holding everything you would otherwise have to choose between.',
        'conversational'    => 'When a plain list of links loses people, this walks them to the right one instead.',
        'slides'            => 'For a story that is better swiped than scrolled: a launch, a lookbook, a short pitch.',
        'ai-chatbot'        => 'For the questions you answer twenty times a week, answered from your own pages while you sleep.',
        'restaurant-menu'   => 'A QR on the table that opens today\'s menu, so a price change does not mean a reprint.',
        'store-menu'        => 'Take orders before you have a checkout: browse by category, request, and you confirm.',
        'file-share'        => 'Send a price list, a rider or a brochure as a link rather than a heavy attachment.',
        'event'             => 'Put the date straight into someone\'s calendar instead of asking them to type it in.',
        'calendar'          => 'For a season of dates, gigs, classes or drops, that people can subscribe to once.',
        'contact-card'      => 'Swap details in one tap at an event, with nothing to type on either side.',
        'resume-portfolio'  => 'One link on an application that shows the work and downloads as a PDF.',
        'business-profile'  => 'The address you give a customer who asked what you do, with hours, location and contact.',
        'reviews-page'      => 'Collect the good word where you can point at it, rather than losing it in DMs.',
        'brand-press-kit'   => 'For anyone who asks for your logo: one link, correct files, no back and forth.',
        'paid-page'         => 'Put a page behind a payment when the content is the product.',
        'qr-code'           => 'For the offline half of your audience: a code that keeps working after you change the destination.',
        'forms'             => 'Ask the question once and keep every answer in one inbox with your chats.',
    ];

    /** The usage line for a link type name, or an empty string when unknown. */
    public static function forName(string $name): string
    {
        return self::LINES[Str::slug(trim($name))] ?? '';
    }
}
