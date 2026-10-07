<?php

namespace Tests\Feature;

use App\Models\Like;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// ヘッダーの検索欄（案内の文字と、検索した文字の扱い）
class SearchFieldTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private const GUIDE = 'なにをお探しですか？';

    private $user;
    private $shoes;
    private $watch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUserWithProfile();
        $seller = $this->createUserWithProfile();
        $this->shoes = $this->createItem($seller, ['item_name' => '革靴']);
        $this->watch = $this->createItem($seller, ['item_name' => '腕時計']);
    }

    private function field(string $value): string
    {
        return 'name="keyword" placeholder="' . self::GUIDE . '" value="' . $value . '"';
    }

    // 案内の文字は placeholder で、欄の値は空
    public function testGuideTextIsPlaceholder(): void
    {
        foreach (['/', '/mypage', '/item/' . $this->shoes->id] as $url) {
            $this->actingAs($this->user)->get($url)
                ->assertStatus(200)
                ->assertSee($this->field(''), false)
                ->assertDontSee('value="' . self::GUIDE . '"', false);
        }
    }

    // 空のまま検索すると、すべての商品が並ぶ（案内の文字では検索されない）
    public function testSearchingWithEmptyFieldShowsAllItems(): void
    {
        $response = $this->actingAs($this->user)->post('/search', ['keyword' => '']);

        $response->assertStatus(200);
        $response->assertSee('革靴');
        $response->assertSee('腕時計');
        $response->assertSee($this->field(''), false);
        $response->assertDontSee('該当する商品はありません。');
    }

    // 検索した文字は、検索後も欄に残る
    public function testSearchedKeywordStaysInField(): void
    {
        $response = $this->actingAs($this->user)->post('/search', ['keyword' => '靴']);

        $response->assertStatus(200);
        $response->assertSee('革靴');
        $response->assertDontSee('腕時計');
        $response->assertSee($this->field('靴'), false);
    }

    // 1 件も見つからなかったときも残る
    public function testKeywordStaysInFieldWithoutMatches(): void
    {
        $this->actingAs($this->user)->post('/search', ['keyword' => 'no-such-item'])
            ->assertSee($this->field('no-such-item'), false)
            ->assertSee('該当する商品はありません。');
    }

    // 検索のあとマイリストに切り替えても残る（絞り込みが続いているので）
    public function testKeywordStaysInFieldOnMylist(): void
    {
        Like::create(['user_id' => $this->user->id, 'item_id' => $this->shoes->id]);
        Like::create(['user_id' => $this->user->id, 'item_id' => $this->watch->id]);

        $response = $this->actingAs($this->user)->get('/?tab=mylist&keyword=' . urlencode('靴'));

        $response->assertSee('革靴');
        $response->assertDontSee('腕時計');
        $response->assertSee($this->field('靴'), false);
    }

    // 「おすすめ」に戻ると、検索は解除されて欄も空になる
    public function testFieldIsEmptyAfterLeavingSearch(): void
    {
        $this->actingAs($this->user)->post('/search', ['keyword' => '靴']);

        $this->get('/')->assertSee('腕時計')->assertSee($this->field(''), false);
    }

    // 検索した文字は、エスケープして欄に戻す
    public function testKeywordIsEscapedInField(): void
    {
        $response = $this->actingAs($this->user)->post('/search', ['keyword' => '"><script>alert(1)</script>']);

        $response->assertStatus(200);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false);
    }
}
