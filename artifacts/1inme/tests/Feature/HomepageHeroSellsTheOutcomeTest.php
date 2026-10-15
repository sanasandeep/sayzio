<?php
namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageHeroSellsTheOutcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_journey_is_readable_without_javascript_and_guest_can_register(): void
    {
        $response=$this->get('/')->assertOk()->assertSee('From your first visitor')->assertSee('repeat customer')->assertSee('Illustrative journey')->assertSee('Alex Morgan')->assertSee('CRM')->assertSee('Delivery &amp; support',false)->assertSee('Start free')->assertSee('See how Sayzio works');
        $dom=new \DOMDocument(); @$dom->loadHTML($response->getContent()); $xpath=new \DOMXPath($dom);
        $this->assertSame(7,$xpath->query('//*[@data-journey-step]')->length);
        $cta=$xpath->query('//*[@id="hero"]//a[contains(@class,"sj-primary")]')->item(0);
        $this->assertSame(route('register.page'),$cta->getAttribute('href'));
        if(getenv('HERO_UI_FIXTURE'))file_put_contents('/tmp/customer-journey-guest.html',$response->getContent());
    }

    public function test_signed_in_cta_opens_dashboard(): void
    {
        $response=$this->actingAs(User::factory()->create())->get('/')->assertOk();
        $dom=new \DOMDocument(); @$dom->loadHTML($response->getContent()); $xpath=new \DOMXPath($dom);
        $cta=$xpath->query('//*[@id="hero"]//a[contains(@class,"sj-primary")]')->item(0);
        $this->assertSame(route('user.dashboard'),$cta->getAttribute('href'));
        $this->assertStringContainsString('Go to dashboard',$cta->textContent);
        if(getenv('HERO_UI_FIXTURE'))file_put_contents('/tmp/customer-journey-auth.html',$response->getContent());
    }

    public function test_previous_shipped_bundle_upgrades_but_custom_copy_is_preserved(): void
    {
        $copy=SitePagesContent::normalizeHeroCopy(['headline'=>'Never miss another customer.','highlight'=>'customer','subheading'=>'One link for your page, your QR codes and your short links. And when you are busy, **your AI Zio answers your visitors and picks up your calls**. **Free forever**, no card.']);
        $this->assertStringContainsString('repeat customer',$copy['headline']);
        $copy=SitePagesContent::normalizeHeroCopy(['headline'=>'Custom title','subheading'=>'Custom content']);
        $this->assertSame('Custom title',$copy['headline']);
        $this->assertSame('Custom content',$copy['subheading']);
    }
}
