<?php

namespace Tests\Feature\Components;

use App\View\Components\SectionHeader;
use Tests\TestCase;

class SectionHeaderTest extends TestCase
{
    public function test_renders_basic_header()
    {
        // Arrange & Act
        $view = $this->component(SectionHeader::class, [
            'title' => '你可能也喜歡',
        ]);

        // Assert
        $view->assertSee('你可能也喜歡')
            ->assertSee('ts large dividing header');
    }

    public function test_always_includes_hidden_divider()
    {
        // Arrange & Act
        $view = $this->component(SectionHeader::class, [
            'title' => '測試標題',
        ]);

        // Assert
        $view->assertSee('class="ts hidden divider"', false);
    }

    public function test_renders_header_with_sub_title()
    {
        // Arrange & Act
        $view = $this->component(SectionHeader::class, [
            'title' => 'StyleHint 網友穿搭靈感',
            'subTitle' => '共 10 張',
        ]);

        // Assert
        $view->assertSee('StyleHint 網友穿搭靈感')
            ->assertSee('共 10 張')
            ->assertSee('class="inline sub header"', false);
    }

    public function test_renders_header_with_right_action_slot()
    {
        // Arrange & Act
        $view = $this->component(SectionHeader::class, [
            'title' => 'StyleHint 網友穿搭靈感',
            'rightAction' => '<a href="/style-hints" class="ts icon labeled button"><i class="camera retro icon"></i>查看列表</a>',
        ]);

        // Assert
        $view->assertSee('StyleHint 網友穿搭靈感')
            ->assertSee('class="right floated"', false)
            ->assertSee('href="/style-hints"', false)
            ->assertSee('查看列表');
    }

    public function test_renders_with_all_features_combined()
    {
        // Arrange & Act
        $view = $this->component(SectionHeader::class, [
            'title' => 'StyleHint 網友穿搭靈感',
            'subTitle' => '共 10 張',
            'rightAction' => '<a class="ts icon labeled button" style="font-size: 0.9rem;" href="/style-hints"><i class="camera retro icon"></i>查看列表</a>',
        ]);

        // Assert
        $view
            // 標題相關
            ->assertSee('StyleHint 網友穿搭靈感')
            ->assertSee('ts large dividing header')
            // 副標題相關
            ->assertSee('共 10 張')
            ->assertSee('class="inline sub header"', false)
            // 右側按鈕相關
            ->assertSee('class="ts icon labeled button"', false)
            ->assertSee('style="font-size: 0.9rem;"', false)
            ->assertSee('class="right floated"', false)
            ->assertSee('href="/style-hints"', false)
            ->assertSee('<i class="camera retro icon"></i>', false)
            ->assertSee('查看列表')
            // 隱藏分隔線
            ->assertSee('class="ts hidden divider"', false);
    }

    public function test_escapes_html_in_title_and_subtitle()
    {
        // Arrange & Act
        $view = $this->component(SectionHeader::class, [
            'title' => "<script>alert('xss')</script>",
            'subTitle' => "<script>alert('xss')</script>",
        ]);

        // Assert
        $view->assertDontSee('<script>', false)
            ->assertDontSee('</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('&lt;/script&gt;', false);
    }

    public function test_handles_special_characters()
    {
        // Arrange & Act
        $specialTitle = '特殊字元：!@#$%^&*()_+{}|:"<>?~`-=[]\\;\',./';
        $view = $this->component(SectionHeader::class, [
            'title' => $specialTitle,
        ]);

        // Assert
        $view->assertSee($specialTitle);
    }

    public function test_handles_empty_right_action()
    {
        // Arrange & Act
        $view = $this->component(SectionHeader::class, [
            'title' => '標題',
            'rightAction' => '',
        ]);

        // Assert
        $view->assertSee('標題')
            ->assertDontSee('class="right floated"', false);
    }
}
