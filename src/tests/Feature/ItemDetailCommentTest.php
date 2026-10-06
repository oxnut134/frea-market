<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 商品詳細に出る最新のコメントと、その投稿者の表示
class ItemDetailCommentTest extends TestCase
{
    use RefreshDatabase, InteractsWithCheckout;

    private $viewer;
    private $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = $this->createUserWithProfile(['profile_image' => 'profiles/viewer.png']);
        $this->viewer->update(['name' => 'viewerName']);
        $this->item = $this->createItem($this->createUserWithProfile());
    }

    private function commenter(string $name, array $profile = []): User
    {
        $user = $this->createUserWithProfile($profile);
        $user->update(['name' => $name]);

        return $user;
    }

    private function comment(User $user, string $text, $created_at = null): Comment
    {
        return Comment::forceCreate([
            'item_id' => $this->item->id,
            'user_id' => $user->id,
            'comment' => $text,
            'created_at' => $created_at ?? now(),
            'updated_at' => $created_at ?? now(),
        ]);
    }

    private function getDetail()
    {
        return $this->actingAs($this->viewer)->get('/item/' . $this->item->id);
    }

    // 名前と画像は、コメントを書いた人のもの（見ている本人のものではない）
    public function testLatestCommentShowsItsAuthor(): void
    {
        $commenter = $this->commenter('commenterName', ['profile_image' => 'profiles/commenter.png']);
        $this->comment($commenter, 'looks good');

        $response = $this->getDetail();

        $response->assertStatus(200);
        $response->assertSee('コメント(1)');
        $response->assertSee('looks good');
        $response->assertSee('<div class="detail-form_user_name">commenterName</div>', false);
        $response->assertSee($commenter->profile->image_url, false);
        $response->assertDontSee('viewerName');
        $response->assertDontSee('profiles/viewer.png', false);
    }

    // 自分が書いたコメントなら、自分の名前が出る
    public function testOwnCommentShowsOwnName(): void
    {
        $this->comment($this->viewer, 'my comment');

        $response = $this->getDetail();

        $response->assertSee('<div class="detail-form_user_name">viewerName</div>', false);
        $response->assertSee('profiles/viewer.png', false);
    }

    // 画像を登録していない投稿者には、既定のアイコンを出す
    public function testAuthorWithoutProfileImageGetsDefaultIcon(): void
    {
        $this->comment($this->commenter('commenterName'), 'looks good');

        $response = $this->getDetail();

        $response->assertSee('<div class="detail-form_user_name">commenterName</div>', false);
        $response->assertSee((new Profile)->image_url, false);
    }

    // 複数あっても、出るのは最新の 1 件とその投稿者
    public function testOnlyLatestCommentIsShown(): void
    {
        $this->comment($this->commenter('olderName'), 'older comment', now()->subMinutes(10));
        $this->comment($this->commenter('newerName'), 'newer comment', now()->subMinutes(5));

        $response = $this->getDetail();

        $response->assertSee('コメント(2)');
        $response->assertSee('newer comment');
        $response->assertSee('<div class="detail-form_user_name">newerName</div>', false);
        $response->assertDontSee('older comment');
        $response->assertDontSee('olderName');
    }

    // 同じ時刻に書かれたコメントは、あとから書かれたものが最新
    public function testCommentsAtSameTimeAreOrderedById(): void
    {
        $at = now()->subMinute()->startOfSecond();
        $this->comment($this->commenter('firstName'), 'first comment', $at);
        $this->comment($this->commenter('secondName'), 'second comment', $at);

        $response = $this->getDetail();

        $response->assertSee('second comment');
        $response->assertDontSee('first comment');
    }

    // コメントがなければ、名前も画像も出さない（見ている本人のものも出さない）
    public function testNoAuthorIsShownWithoutComments(): void
    {
        $response = $this->getDetail();

        $response->assertStatus(200);
        $response->assertSee('コメント(0)');
        $response->assertDontSee('detail-form_user_name', false);
        $response->assertDontSee('viewerName');
        $response->assertDontSee('profiles/viewer.png', false);
    }

    // 未ログインでも、見出しと最新のコメント、その投稿者が見られる
    public function testGuestSeesLatestCommentAndItsAuthor(): void
    {
        $this->comment($this->commenter('olderName'), 'older comment', now()->subMinutes(10));
        $commenter = $this->commenter('commenterName', ['profile_image' => 'profiles/commenter.png']);
        $this->comment($commenter, 'looks good', now()->subMinutes(5));

        $response = $this->get('/item/' . $this->item->id);

        $response->assertStatus(200);
        $this->assertGuest();
        $response->assertSee('コメント(2)');
        $response->assertSee('looks good');
        $response->assertSee('<div class="detail-form_user_name">commenterName</div>', false);
        $response->assertSee($commenter->profile->image_url, false);
        $response->assertDontSee('older comment');
    }

    // 未ログインでは、入力欄と送信ボタンの代わりに、ログインして同じ商品に戻るリンクが出る
    public function testGuestSeesLoginLinkInsteadOfCommentForm(): void
    {
        $response = $this->get('/item/' . $this->item->id);

        $response->assertSee('コメント(0)');
        $response->assertSee('<a class="detail-form_login_to_comment_link" href="/item/' . $this->item->id . '/login">ログインしてコメントする</a>', false);
        $response->assertDontSee('<textarea', false);
        $response->assertDontSee('action="/item/comment"', false);
        $response->assertDontSee('コメントを送信する');
    }

    // ログイン済みでは、入力欄と送信ボタンが出て、リンクは出ない
    public function testLoggedInUserSeesCommentForm(): void
    {
        $response = $this->getDetail();

        $response->assertSee('action="/item/comment"', false);
        $response->assertSee('<textarea', false);
        $response->assertSee('コメントを送信する');
        $response->assertDontSee('ログインしてコメントする');
    }
}
