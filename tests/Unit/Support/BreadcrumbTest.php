<?php

namespace Tests\Unit\Support;

use App\Support\Breadcrumb;
use Tests\TestCase;

class BreadcrumbTest extends TestCase
{
    /**
     * config/nav.php 的 links 每一項都可能被 Breadcrumb::link() 消費（那正是
     * 這個 partial 存在的目的），所以每一個 key 都要能安全呼叫，不能因為
     * route() 需要參數就丟 UrlGenerationException。search 曾經指到需要參數的
     * search.show，改成 search.index 才是不需要參數的入口。
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
