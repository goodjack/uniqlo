<?php

namespace Tests\Feature\Components;

use App\View\Components\Section;
use Tests\TestCase;

class SectionTest extends TestCase
{
    public function test_renders_basic_section()
    {
        // Arrange & Act
        $view = $this->component(Section::class);

        // Assert - 分別驗證各個 CSS 類別，提高測試的可維護性
        $view->assertSee('ts very padded')
            ->assertSee('horizontally fitted')
            ->assertSee('attached fluid')
            ->assertSee('segment')
            ->assertSee('ts container');
    }

    public function test_renders_with_title()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'title' => '測試標題',
        ]);

        // Assert
        $view->assertSee('測試標題')
            ->assertSee('ts large dividing header');
    }

    public function test_renders_with_subtitle()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'title' => '測試標題',
            'subTitle' => '副標題',
        ]);

        // Assert
        $view->assertSee('測試標題')
            ->assertSee('副標題')
            ->assertSee('class="inline sub header"', false);
    }

    public function test_renders_with_right_action()
    {
        // Arrange & Act
        $view = $this->blade(
            '<x-section title="測試標題">
                <x-slot:rightAction>
                    <a href="#" class="ts button">按鈕</a>
                </x-slot:rightAction>
            </x-section>'
        );

        // Assert
        $view->assertSee('測試標題')
            ->assertSee('class="right floated"', false)
            ->assertSee('class="ts button"', false)
            ->assertSee('按鈕');
    }

    public function test_renders_with_secondary_style()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'secondary' => true,
        ]);

        // Assert - 分別驗證 secondary segment 的 CSS 類別
        $view->assertSee('ts very padded')
            ->assertSee('horizontally fitted')
            ->assertSee('attached fluid')
            ->assertSee('secondary segment')
            ->assertSee('ts container');
    }

    public function test_renders_with_tertiary_style()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'tertiary' => true,
        ]);

        // Assert - 分別驗證 tertiary segment 的 CSS 類別
        $view->assertSee('ts very padded')
            ->assertSee('horizontally fitted')
            ->assertSee('attached fluid')
            ->assertSee('tertiary segment')
            ->assertSee('ts container');
    }

    public function test_renders_with_grid()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'grid' => 'stackable',
        ]);

        // Assert
        $view->assertSee('ts container stackable grid');
    }

    public function test_renders_with_relaxed_stackable_grid()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'grid' => 'relaxed stackable',
        ]);

        // Assert
        $view->assertSee('ts container relaxed stackable grid');
    }

    public function test_renders_with_very_narrow_container()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'veryNarrow' => true,
        ]);

        // Assert
        $view->assertSee('ts very narrow container');
    }

    public function test_renders_with_slot_content()
    {
        // Arrange & Act
        $view = $this->blade(
            '<x-section>
                <p>測試內容</p>
            </x-section>'
        );

        // Assert
        $view->assertSee('測試內容');
    }

    public function test_renders_with_combined_features()
    {
        // Arrange & Act
        $view = $this->blade(
            '<x-section title="測試標題" subTitle="副標題" secondary grid="relaxed stackable" veryNarrow>
                <x-slot:rightAction>
                    <a href="#" class="ts button">按鈕</a>
                </x-slot:rightAction>
            </x-section>'
        );

        // Assert
        $view->assertSee('測試標題')
            ->assertSee('副標題')
            ->assertSee('按鈕')
            ->assertSee('secondary segment')
            ->assertSee('ts very padded')
            ->assertSee('horizontally fitted')
            ->assertSee('ts very narrow container relaxed stackable grid');
    }

    public function test_handles_empty_grid_string()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'grid' => '',
        ]);

        // Assert
        $view->assertDontSee('grid')
            ->assertSee('ts container');
    }

    public function test_escapes_html_in_title_and_subtitle()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'title' => "<script>alert('xss')</script>",
            'subTitle' => "<script>alert('xss')</script>",
        ]);

        // Assert
        $view->assertDontSee('<script>', false)
            ->assertDontSee('</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('&lt;/script&gt;', false);
    }

    public function test_subtitle_not_rendered_without_title()
    {
        // Arrange & Act - 測試沒有標題時，副標題不會被渲染
        $view = $this->component(Section::class, [
            'subTitle' => '只有副標題',
        ]);

        // Assert - 確認副標題和相關的 HTML 結構都不會出現
        $view->assertDontSee('只有副標題', false)
            ->assertDontSee('class="inline sub header"', false);
    }

    public function test_right_action_not_rendered_without_title()
    {
        // Arrange & Act - 測試沒有標題時，右側動作不會被渲染
        $view = $this->blade(
            '<x-section>
                <x-slot:rightAction>
                    <a href="#" class="ts button">按鈕</a>
                </x-slot:rightAction>
            </x-section>'
        );

        // Assert - 確認右側動作和相關的 HTML 結構都不會出現
        $view->assertDontSee('按鈕')
            ->assertDontSee('class="right floated"', false);
    }

    public function test_renders_with_both_secondary_and_tertiary_styles()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'secondary' => true,
            'tertiary' => true,
        ]);

        // Assert
        $view->assertSee('secondary segment')
            ->assertDontSee('tertiary segment');
    }

    public function test_renders_with_empty_title()
    {
        // Arrange & Act
        $view = $this->blade(
            '<x-section title="" subTitle="副標題">
                <x-slot:rightAction>
                    <a href="#" class="ts button">按鈕</a>
                </x-slot:rightAction>
            </x-section>'
        );

        // Assert
        $view->assertDontSee('class="ts large dividing header"', false)
            ->assertDontSee('副標題')
            ->assertDontSee('按鈕');
    }

    public function test_renders_with_null_values()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'title' => null,
            'subTitle' => null,
            'grid' => null,
        ]);

        // Assert - 驗證空 grid 字串的處理
        $view->assertSee('ts very padded')
            ->assertSee('horizontally fitted')
            ->assertSee('attached fluid')
            ->assertSee('segment')
            ->assertSee('ts container')
            ->assertDontSee('grid');
    }

    public function test_renders_with_special_characters()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'title' => '特殊字元：&<>"\'',
            'subTitle' => '特殊字元：&<>"\'',
        ]);

        // Assert
        $view->assertSee('特殊字元：&amp;&lt;&gt;&quot;&#039;', false);
    }

    public function test_renders_with_multiple_grid_classes()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'grid' => 'relaxed stackable doubling',
        ]);

        // Assert
        $view->assertSee('ts container relaxed stackable doubling grid');
    }

    public function test_renders_with_named_slot_right_action()
    {
        // Arrange & Act
        $view = $this->blade(
            '<x-section title="測試標題">
                <x-slot:rightAction>
                    <button class="ts primary button">
                        <i class="plus icon"></i>新增
                    </button>
                </x-slot:rightAction>
                <p>內容區塊</p>
            </x-section>'
        );

        // Assert
        $view->assertSee('測試標題')
            ->assertSee('class="right floated"', false)
            ->assertSee('class="ts primary button"', false)
            ->assertSee('<i class="plus icon"></i>新增', false)
            ->assertSee('內容區塊');
    }

    public function test_always_includes_hidden_divider()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'title' => '測試標題',
        ]);

        // Assert
        $view->assertSee('class="ts hidden divider"', false);
    }

    public function test_handles_empty_right_action()
    {
        // Arrange & Act
        $view = $this->blade(
            '<x-section title="標題">
            </x-section>'
        );

        // Assert
        $view->assertSee('標題')
            ->assertDontSee('class="right floated"', false);
    }

    public function test_renders_with_inverted_style()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'inverted' => true,
        ]);

        // Assert
        $view->assertSee('inverted');
    }

    public function test_renders_with_inverted_and_secondary_style()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'inverted' => true,
            'secondary' => true,
        ]);

        // Assert
        $view->assertSee('secondary segment')
            ->assertSee('inverted');
    }

    public function test_renders_with_inverted_and_tertiary_style()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'inverted' => true,
            'tertiary' => true,
        ]);

        // Assert
        $view->assertSee('tertiary segment')
            ->assertSee('inverted');
    }

    public function test_renders_inverted_with_title_and_subtitle()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'title' => '測試標題',
            'subTitle' => '副標題',
            'inverted' => true,
        ]);

        // Assert
        $view->assertSee('測試標題')
            ->assertSee('副標題')
            ->assertSee('inverted');
    }

    public function test_renders_with_default_very_padded()
    {
        // Arrange & Act
        $view = $this->component(Section::class);

        // Assert
        $view->assertSee('very padded')
            ->assertDontSee('class="ts padded"', false);
    }

    public function test_renders_with_normal_padded()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'padded' => 'normal',
        ]);

        // Assert
        $view->assertDontSee('very padded')
            ->assertSee('padded');
    }

    public function test_renders_without_padding()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'padded' => 'none',
        ]);

        // Assert
        $view->assertDontSee('very padded')
            ->assertDontSee('padded');
    }

    public function test_renders_padded_with_other_styles()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'padded' => 'normal',
            'secondary' => true,
            'inverted' => true,
        ]);

        // Assert
        $view->assertSee('padded')
            ->assertDontSee('very padded')
            ->assertSee('secondary segment')
            ->assertSee('inverted');
    }
}
