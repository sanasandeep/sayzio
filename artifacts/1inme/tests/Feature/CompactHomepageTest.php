<?php
namespace Tests\Feature;

use Tests\TestCase;
use Tests\Support\RendersTheHomepage;

class CompactHomepageTest extends TestCase
{
    use RendersTheHomepage;

    public function test_home_explains_the_connected_workflow_without_repeated_tours(): void
    {
        $initial=$this->initialHomepageHtml();
        $fragment=$this->deferredHomepageHtml();
        $html=$initial.$fragment;
        foreach(['customer-workflow','connected-ai','create'] as $id){
            $this->assertStringContainsString('id="'.$id.'"',$initial);
            $this->assertStringNotContainsString('id="'.$id.'"',$fragment);
        }
        foreach(['audience','domains','grow','workspace-team','buzz','blog-featured'] as $id){
            $this->assertStringNotContainsString('id="'.$id.'"',$html);
        }
        $this->assertSame(1,substr_count($html,'class="ch-trust pb-band"'));
        $this->assertStringNotContainsString('class="sj-proof"',$html);
        $dom=new \DOMDocument();@$dom->loadHTML($initial);$xp=new \DOMXPath($dom);
        $this->assertSame(7,$xp->query('//*[@data-home-tabs]//*[@role="tab"]')->length);
        foreach($xp->query('//*[@data-home-tabs]//*[@role="tab"]') as $tab){
            $this->assertSame(1,$xp->query('//*[@id="'.$tab->getAttribute('aria-controls').'"]')->length);
        }
        if(getenv('COMPACT_HOME_UI_FIXTURE')){
            file_put_contents('/tmp/compact-home.html',$initial);
            file_put_contents('/tmp/compact-home-fragment.html',$fragment);
        }
    }

    public function test_faq_schema_matches_the_four_visible_answers(): void
    {
        $fragment=$this->deferredHomepageHtml();
        $dom=new \DOMDocument();@$dom->loadHTML($fragment);$xp=new \DOMXPath($dom);
        $questions=$xp->query('//*[@id="faq"]//summary');
        $this->assertSame(4,$questions->length);
        $schema=null;
        foreach($xp->query('//script[@type="application/ld+json"]') as $script){
            foreach(json_decode($script->textContent,true)['@graph']??[] as $node){
                if(($node['@type']??'')==='FAQPage')$schema=$node;
            }
        }
        $this->assertNotNull($schema);
        foreach($questions as $i=>$question){
            $this->assertSame($schema['mainEntity'][$i]['name'],trim(str_replace('+','',$question->textContent)));
        }
        $this->assertStringContainsString(route('site.pricing'),$fragment);
        $this->assertStringContainsString(route('register.page'),$fragment);
        $this->assertStringNotContainsString('Marketing Strategist',$fragment);
    }
}
