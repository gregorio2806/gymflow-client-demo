<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the touch-target sizes from docs/design/ui-guidelines.md §4 (44px minimum),
 * so re-generating shadcn-vue components can't silently shrink them back to 36px.
 */
class UiSizeStandardsTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/resources/js/components/'.$path);
    }

    public function test_default_button_and_icon_button_are_44px()
    {
        $button = $this->source('ui/button/index.ts');

        $this->assertStringContainsString('"default": "h-11 ', $button);
        $this->assertStringContainsString('"icon": "size-11"', $button);
    }

    public function test_inputs_and_selects_are_44px()
    {
        $this->assertStringContainsString(' h-11 w-full', $this->source('ui/input/Input.vue'));
        $this->assertStringContainsString('data-[size=default]:h-11', $this->source('ui/select/SelectTrigger.vue'));
        $this->assertStringContainsString('h-11 w-full', $this->source('NativeSelect.vue'));
    }
}
