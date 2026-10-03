<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Profile;

class ExhibitPriceValidationTest extends TestCase
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
    }

    private function exhibit($price)
    {
        $category = Category::create(['category' => 'ファッション']);

        return $this->post('/sell', [
            'item_image' => UploadedFile::fake()->image('shoes.jpg'),
            'item_name' => '革靴',
            'description' => 'クラシックなデザインの革靴',
            'condition' => '良好',
            'categories' => [$category->category],
            'price' => $price,
        ]);
    }

    // 120円は出品できる
    public function testPriceOf120YenIsAccepted(): void
    {
        $this->exhibit(120)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('items', ['item_name' => '革靴', 'price' => 120]);
    }

    // 120円未満は出品できない
    public function testPriceBelow120YenIsRejected(): void
    {
        foreach ([119, 0] as $price) {
            $this->exhibit($price)->assertSessionHasErrors(['price' => '商品価格は120円以上で入力してください。']);
        }
        $this->assertDatabaseCount('items', 0);
    }

    // 数値でない価格は出品できない
    public function testNonNumericPriceIsRejected(): void
    {
        $this->exhibit('abc')->assertSessionHasErrors(['price' => '商品価格は数値で入力してください。']);
        $this->assertDatabaseCount('items', 0);
    }

    // 価格が空の場合は出品できない
    public function testEmptyPriceIsRejected(): void
    {
        $this->exhibit('')->assertSessionHasErrors(['price' => '商品価格を入力してください。']);
        $this->assertDatabaseCount('items', 0);
    }
}
