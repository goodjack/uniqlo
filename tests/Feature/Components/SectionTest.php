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

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid segment')
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
        $view = $this->component(Section::class, [
            'title' => '測試標題',
            'rightAction' => '<a href="#" class="ts button">按鈕</a>',
        ]);

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

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid secondary segment')
            ->assertSee('ts container');
    }

    public function test_renders_with_tertiary_style()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'tertiary' => true,
        ]);

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid tertiary segment')
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
        $view = $this->component(Section::class, [
            'title' => '測試標題',
            'subTitle' => '副標題',
            'rightAction' => '<a href="#" class="ts button">按鈕</a>',
            'secondary' => true,
            'grid' => 'relaxed stackable',
            'veryNarrow' => true,
        ]);

        // Assert
        $view->assertSee('測試標題')
            ->assertSee('副標題')
            ->assertSee('按鈕')
            ->assertSee('ts very padded horizontally fitted attached fluid secondary segment')
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

    public function test_renders_with_only_subtitle()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'subTitle' => '只有副標題',
        ]);

        // Assert
        $view->assertDontSee('只有副標題', false)
            ->assertDontSee('class="inline sub header"', false);
    }

    public function test_renders_with_only_right_action()
    {
        // Arrange & Act
        $view = $this->component(Section::class, [
            'rightAction' => '<a href="#" class="ts button">按鈕</a>',
        ]);

        // Assert
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
        $view = $this->component(Section::class, [
            'title' => '',
            'subTitle' => '副標題',
            'rightAction' => '<a href="#" class="ts button">按鈕</a>',
        ]);

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
            'rightAction' => null,
            'grid' => null,
        ]);

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid segment')
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
        $view = $this->component(Section::class, [
            'title' => '標題',
            'rightAction' => '',
        ]);

        // Assert
        $view->assertSee('標題')
            ->assertDontSee('class="right floated"', false);
    }
}
