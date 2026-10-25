<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Profile;

// 出品の画面：入力の案内、エラーで戻ったときに入力値を残すこと、成功したあとの移り先
class ExhibitFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $user = User::factory()->create();
        Profile::create([
            'user_id' => $user->id,
            'post_code' => '111-1111',
            'address' => 'Tokyo',
        ]);
        $this->actingAs($user);

        Category::create(['category' => 'ファッション']);
        Category::create(['category' => 'メンズ']);
        Category::create(['category' => 'キッチン']);
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'item_image' => UploadedFile::fake()->image('shoes.jpg'),
            'item_name' => '革靴',
            'brand_name' => 'regal',
            'description' => 'クラシックなデザインの革靴',
            'condition' => 'やや汚れや傷あり',
            'categories' => ['ファッション', 'メンズ'],
            'price' => 4000,
        ], $overrides);
    }

    // 画像の欄は JPEG と PNG だけを選ばせ、形式と大きさ、価格の下限を案内する
    public function testFormShowsImageRestrictionsAndPriceMinimum(): void
    {
        $this->get('/sell')
            ->assertOk()
            ->assertSee('accept="image/jpeg,image/png"', false)
            ->assertSee('JPEG・PNG、5MB 以下')
            ->assertSee('120 円以上');
    }

    // 必須の項目（画像、カテゴリー、商品の状態、商品名、商品の説明、販売価格）にだけ「必須」を付ける
    public function testRequiredMarksAreShownOnlyOnRequiredFields(): void
    {
        $html = $this->get('/sell')->getContent();
        $mark = '<span class="exhibit-form_required">必須</span>';

        $this->assertSame(6, substr_count($html, $mark));
        foreach (['カテゴリー', '商品の状態', '商品名', '商品の説明', '販売価格'] as $title) {
            $this->assertStringContainsString($title . $mark, $html);
        }
        $this->assertStringContainsString($mark . 'JPEG・PNG、5MB 以下', $html);
        $this->assertStringNotContainsString('ブランド名' . $mark, $html);
    }

    // 画像なしで送ると出品の画面に戻り、画像以外の入力値が残っている
    public function testInputIsKeptWhenValidationFails(): void
    {
        $response = $this->from('/sell')->followingRedirects()
            ->post('/sell', $this->input(['item_image' => null]));

        $response->assertOk()
            ->assertSee('画像ファイルを選択してください。')
            ->assertSee('value="革靴"', false)
            ->assertSee('value="regal"', false)
            ->assertSee('>クラシックなデザインの革靴</textarea>', false)
            ->assertSee('value="4000"', false)
            ->assertSee('<option selected>やや汚れや傷あり</option>', false)
            ->assertSee('<option>良好</option>', false)
            ->assertSee('value="ファッション" checked', false)
            ->assertSee('value="メンズ" checked', false)
            ->assertSee('value="キッチン">', false);
        $this->assertDatabaseCount('items', 0);
    }

    // 出品できたら、マイページ（出品した商品）へ移り、「出品しました。」と表示する
    public function testRedirectsToMyPageWithMessageAfterExhibiting(): void
    {
        $this->post('/sell', $this->input())
            ->assertRedirect(route('mypage'))
            ->assertSessionHas('message', '出品しました。');

        $this->get('/mypage')
            ->assertSee('出品しました。')
            ->assertSee('革靴');
        $this->assertDatabaseHas('items', ['item_name' => '革靴', 'price' => 4000]);
    }

    // お知らせは 1 回だけ表示する（再読み込みでは出ない）
    public function testMessageIsShownOnlyOnce(): void
    {
        $this->post('/sell', $this->input());
        $this->get('/mypage')->assertSee('出品しました。');
        $this->get('/mypage')->assertDontSee('出品しました。');
    }
}
