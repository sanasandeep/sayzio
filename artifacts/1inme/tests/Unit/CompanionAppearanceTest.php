<?php
namespace Tests\Unit;
use App\Services\AI\CompanionAppearance;
use PHPUnit\Framework\TestCase;
class CompanionAppearanceTest extends TestCase
{
 public function test_gradients_colors_and_radius_are_bounded(): void
 {
  $v=CompanionAppearance::variables(['accent'=>'#123456','chat_mode'=>'gradient','chat_color'=>'#112233','chat_color_end'=>'#aabbcc','chat_text'=>'#ffffff','bubble_radius'=>99]);
  $this->assertSame('#123456',$v['--cmp-accent-bg']);
  $this->assertSame('linear-gradient(135deg,#112233,#aabbcc)',$v['--cmp-chat-bg']);
  $this->assertSame('28px',$v['--cmp-radius']);
 }
 public function test_unsafe_colors_and_image_schemes_are_not_rendered(): void
 {
  $v=CompanionAppearance::variables(['user_mode'=>'solid','user_color'=>'red;position:fixed','assistant_mode'=>'image','assistant_color'=>'#112233','assistant_image'=>'javascript:alert(1)']);
  $this->assertArrayNotHasKey('--cmp-user-bg',$v);$this->assertArrayNotHasKey('--cmp-assistant-bg',$v);
 }
 public function test_default_accent_follows_original_picker_and_image_urls_are_escaped(): void
 {
  $v=CompanionAppearance::variables(['accent'=>'#123456','accent_color'=>'#ffffff','chat_mode'=>'image','chat_color'=>'#112233','chat_image'=>'https://example.com/a%20b.png']);
  $this->assertSame('#123456',$v['--cmp-accent-bg']);$this->assertStringContainsString('https://example.com/a%20b.png',$v['--cmp-chat-bg']);
 }
}
