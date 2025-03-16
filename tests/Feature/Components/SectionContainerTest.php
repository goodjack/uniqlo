<?php

namespace Tests\Feature\Components;

use App\View\Components\SectionContainer;
use Tests\TestCase;

class SectionContainerTest extends TestCase
{
    public function test_renders_basic_container()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class);

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid segment')
            ->assertSee('ts container');
    }

    public function test_renders_with_secondary_style()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class, [
            'secondary' => true,
        ]);

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid secondary segment')
            ->assertSee('ts container');
    }

    public function test_renders_with_tertiary_style()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class, [
            'tertiary' => true,
        ]);

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid tertiary segment')
            ->assertSee('ts container');
    }

    public function test_renders_with_grid()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class, [
            'grid' => 'stackable',
        ]);

        // Assert
        $view->assertSee('ts container stackable grid');
    }

    public function test_renders_with_relaxed_stackable_grid()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class, [
            'grid' => 'relaxed stackable',
        ]);

        // Assert
        $view->assertSee('ts container relaxed stackable grid');
    }

    public function test_renders_with_very_narrow_container()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class, [
            'veryNarrow' => true,
        ]);

        // Assert
        $view->assertSee('ts very narrow container');
    }

    public function test_renders_with_slot_content()
    {
        // Arrange & Act
        $view = $this->blade(
            '<x-section-container>
                <p>測試內容</p>
            </x-section-container>'
        );

        // Assert
        $view->assertSee('測試內容');
    }

    public function test_renders_with_combined_features()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class, [
            'secondary' => true,
            'grid' => 'relaxed stackable',
            'veryNarrow' => true,
        ]);

        // Assert
        $view->assertSee('ts very padded horizontally fitted attached fluid secondary segment')
            ->assertSee('ts very narrow container relaxed stackable grid');
    }

    public function test_handles_empty_grid_string()
    {
        // Arrange & Act
        $view = $this->component(SectionContainer::class, [
            'grid' => '',
        ]);

        // Assert
        $view->assertDontSee('grid')
            ->assertSee('ts container');
    }
}
