<?php

namespace Tests\Feature;

use Tests\TestCase;

class StylesheetCharsetAndVersionTest extends TestCase
{
    // public/css 配下の CSS は、すべて先頭で UTF-8 を宣言している
    public function testEveryStylesheetDeclaresUtf8Charset(): void
    {
        $files = array_merge(glob(public_path('css/*.css')), glob(public_path('css/*/*.css')));
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertStringStartsWith('@charset "UTF-8";', file_get_contents($file), $file);
        }
    }

    // CSS と JS の URL には、ファイルの更新時刻がバージョンとして付く
    public function testStylesheetAndScriptUrlsCarryFileModificationTime(): void
    {
        $response = $this->get('/frea');

        $response->assertStatus(200);
        foreach (['css/sanitize.css', 'css/header.css', 'css/index.css', 'js/submit-guard.js'] as $path) {
            $response->assertSee(asset($path) . '?v=' . filemtime(public_path($path)), false);
        }
    }

    // レイアウトを使わない画面（ログイン）でも同じ
    public function testLoginPageStylesheetUrlCarriesFileModificationTime(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(asset('css/login.css') . '?v=' . filemtime(public_path('css/login.css')), false);
    }
}
