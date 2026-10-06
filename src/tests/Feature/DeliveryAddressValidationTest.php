<?php

namespace Tests\Feature;

use App\Http\Requests\RedirectRequest;
use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 配送先の変更画面の入力の検証
class DeliveryAddressValidationTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $buyer;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = $this->createUserWithProfile();
        $this->item = $this->createItem($this->createUserWithProfile());
    }

    private function changeAddress(array $overrides = [])
    {
        return $this->actingAs($this->buyer)
            ->from('/purchase/address/' . $this->item->id)
            ->post(route('purchase.redirect'), array_merge([
                'item_id' => $this->item->id,
                'post_code' => '123-1234',
                'address' => 'newAddress',
                'building' => 'newBuilding',
            ], $overrides));
    }

    private function assertAddressUnchanged(): void
    {
        $this->assertSame('111-1111', Profile::where('user_id', $this->buyer->id)->value('post_code'));
    }

    public function testValidAddressIsAccepted(): void
    {
        $this->changeAddress([
            'address' => str_repeat('あ', 100),
            'building' => str_repeat('い', 100),
        ])->assertSessionHasNoErrors()->assertRedirect('/purchase/' . $this->item->id);
    }

    /**
     * @dataProvider invalidPostCodes
     */
    public function testPostCodeMustBeThreeDigitsHyphenFourDigits(string $post_code): void
    {
        $this->changeAddress(['post_code' => $post_code])
            ->assertRedirect('/purchase/address/' . $this->item->id)
            ->assertSessionHasErrors(['post_code' => '郵便番号はハイフンありの８文字で入力してください。']);

        $this->assertAddressUnchanged();
    }

    public function invalidPostCodes(): array
    {
        return [
            'ハイフンなし' => ['1231234'],
            '桁が多い' => ['123-12345'],
            '数字以外' => ['abc-defg'],
            '全角' => ['１２３-１２３４'],
        ];
    }

    // 末尾の改行は TrimStrings が先に取り除くので、ルールそのものを確かめる
    public function testPostCodeRuleRejectsTrailingNewline(): void
    {
        $rules = ['post_code' => (new RedirectRequest())->rules()['post_code']];

        $this->assertTrue(Validator::make(['post_code' => '123-1234'], $rules)->passes());
        $this->assertTrue(Validator::make(['post_code' => "123-1234\n"], $rules)->fails());
    }

    // 入力エラーで戻っても、入力した値が欄に残る
    public function testInputIsKeptAfterValidationError(): void
    {
        $this->changeAddress([
            'post_code' => '1231234',
            'address' => 'enteredAddress',
            'building' => 'enteredBuilding',
        ])->assertRedirect('/purchase/address/' . $this->item->id);

        $response = $this->get('/purchase/address/' . $this->item->id);
        $response->assertSee('value="1231234"', false);
        $response->assertSee('value="enteredAddress"', false);
        $response->assertSee('value="enteredBuilding"', false);
    }

    public function testAddressIsLimitedTo100Characters(): void
    {
        $this->changeAddress(['address' => str_repeat('あ', 101)])
            ->assertSessionHasErrors(['address' => '住所は100文字以内で入力してください。']);

        $this->assertAddressUnchanged();
    }

    public function testBuildingIsLimitedTo100Characters(): void
    {
        $this->changeAddress(['building' => str_repeat('あ', 101)])
            ->assertSessionHasErrors(['building' => '建物名は100文字以内で入力してください。']);

        $this->assertAddressUnchanged();
    }

    public function testBuildingIsRequired(): void
    {
        $this->changeAddress(['building' => ''])
            ->assertSessionHasErrors(['building' => '建物名を入力してください。']);

        $this->assertAddressUnchanged();
    }

    public function testUnknownItemIsNotFound(): void
    {
        $unknown_id = $this->item->id + 1000;

        $this->actingAs($this->buyer)->get('/purchase/address/' . $unknown_id)->assertNotFound();
        $this->changeAddress(['item_id' => $unknown_id])->assertNotFound();

        $this->assertAddressUnchanged();
    }

    public function testNonNumericItemIdIsNotFound(): void
    {
        $this->actingAs($this->buyer)->get('/purchase/address/abc')->assertNotFound();
        $this->changeAddress(['item_id' => 'abc'])->assertNotFound();
        $this->changeAddress(['item_id' => ''])->assertNotFound();

        $this->assertAddressUnchanged();
    }
}
