<?php

namespace Tests\Feature;

use Tests\TestCase;

// robots.txt：すべてのクローラーに、すべてのページの巡回を断る
// （ポートフォリオからのリンクで見てもらう前提。クローラーのアクセスで DB を起こさない）
class RobotsTxtTest extends TestCase
{
    public function testRobotsTxtDisallowsEverything(): void
    {
        $this->assertSame("User-agent: *\nDisallow: /\n", file_get_contents(public_path('robots.txt')));
    }
}
