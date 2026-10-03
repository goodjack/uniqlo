<?php

namespace Tests\Unit\Support;

use App\Support\Breadcrumb;
use Tests\TestCase;

class BreadcrumbTest extends TestCase
{
    /**
     * links 裡每一項都要能不帶參數產生網址，Breadcrumb::link() 才能安全呼叫。
     */
    public function test_every_configured_link_resolves_without_a_route_parameter(): void
    {
        foreach (array_keys(config('nav.links')) as $key) {
            $crumb = Breadcrumb::link($key);

            $this->assertIsString($crumb['url'], "nav.links.{$key} 的 route 需要參數，Breadcrumb::link() 呼叫不到");
            $this->assertNotSame('', $crumb['url']);
        }
    }
}
