<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithCheckout;
use Tests\TestCase;

// 商品詳細に出るコメント（すべてを新しい順に）と、その投稿者の表示
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

    // 複数あれば、すべてを新しい順に出す。1 件ごとに、書いた人の名前が付く
    public function testAllCommentsAreShownNewestFirst(): void
    {
        $this->comment($this->commenter('oldestName'), 'oldest comment', now()->subMinutes(15));
        $this->comment($this->commenter('olderName'), 'older comment', now()->subMinutes(10));
        $this->comment($this->commenter('newerName'), 'newer comment', now()->subMinutes(5));

        $response = $this->getDetail();

        $response->assertSee('コメント(3)');
        $response->assertSeeInOrder([
            '<div class="detail-form_user_name">newerName</div>', 'newer comment',
            '<div class="detail-form_user_name">olderName</div>', 'older comment',
            '<div class="detail-form_user_name">oldestName</div>', 'oldest comment',
        ], false);
        $this->assertSame(3, substr_count($response->getContent(), '<li class="detail-form_comment">'));
    }

    // 同じ時刻に書かれたコメントは、あとから書かれたものが上
    public function testCommentsAtSameTimeAreOrderedById(): void
    {
        $at = now()->subMinute()->startOfSecond();
        $this->comment($this->commenter('firstName'), 'first comment', $at);
        $this->comment($this->commenter('secondName'), 'second comment', $at);

        $response = $this->getDetail();

        $response->assertSeeInOrder(['second comment', 'first comment']);
    }

    // コメントがなければ、枠も名前も画像も出さず、案内を出す（見ている本人のものも出さない）
    public function testMessageIsShownWithoutComments(): void
    {
        $response = $this->getDetail();

        $response->assertStatus(200);
        $response->assertSee('コメント(0)');
        $response->assertSee('<p class="detail-form_no_comments">コメントはまだありません。</p>', false);
        $response->assertDontSee('detail-form_comment_list', false);
        $response->assertDontSee('detail-form_user_name', false);
        $response->assertDontSee('viewerName');
        $response->assertDontSee('profiles/viewer.png', false);
    }

    // 未ログインでも、見出しとすべてのコメント、その投稿者が見られる
    public function testGuestSeesAllCommentsAndTheirAuthors(): void
    {
        $this->comment($this->commenter('olderName'), 'older comment', now()->subMinutes(10));
        $commenter = $this->commenter('commenterName', ['profile_image' => 'profiles/commenter.png']);
        $this->comment($commenter, 'looks good', now()->subMinutes(5));

        $response = $this->get('/item/' . $this->item->id);

        $response->assertStatus(200);
        $this->assertGuest();
        $response->assertSee('コメント(2)');
        $response->assertSeeInOrder([
            '<div class="detail-form_user_name">commenterName</div>', 'looks good',
            '<div class="detail-form_user_name">olderName</div>', 'older comment',
        ], false);
        $response->assertSee($commenter->profile->image_url, false);
        $response->assertDontSee('コメントはまだありません。');
    }

    // 同じ人が何件書いても、すべて出る。件数の表示は、並んでいる数と同じ
    public function testSameAuthorCanHaveSeveralComments(): void
    {
        $commenter = $this->commenter('commenterName');
        $this->comment($commenter, 'first one', now()->subMinutes(3));
        $this->comment($commenter, 'second one', now()->subMinutes(2));
        $this->comment($this->viewer, 'mine', now()->subMinute());

        $response = $this->getDetail();

        $response->assertSee('コメント(3)');
        $response->assertSeeInOrder(['mine', 'second one', 'first one']);
        $this->assertSame(3, substr_count($response->getContent(), '<li class="detail-form_comment">'));
        $this->assertSame(2, substr_count($response->getContent(), '<div class="detail-form_user_name">commenterName</div>'));
        $this->assertSame(1, substr_count($response->getContent(), '<div class="detail-form_user_name">viewerName</div>'));
    }

    // ほかの商品のコメントは出ない
    public function testCommentsOfOtherItemsAreNotShown(): void
    {
        $other_item = $this->createItem($this->createUserWithProfile(), ['item_name' => '腕時計']);
        Comment::forceCreate(['item_id' => $other_item->id, 'user_id' => $this->viewer->id, 'comment' => 'about the watch']);
        $this->comment($this->commenter('commenterName'), 'about the shoes');

        $response = $this->getDetail();

        $response->assertSee('コメント(1)');
        $response->assertSee('about the shoes');
        $response->assertDontSee('about the watch');
    }

    // 改行は、そのまま出す（表示は CSS の white-space: pre-wrap に任せ、<br> は差し込まない）。タグはエスケープする
    public function testLineBreaksAreKeptAndHtmlIsEscaped(): void
    {
        $this->comment($this->commenter('commenterName'), "line one\nline two <b>bold</b> <script>alert(1)</script>");

        $response = $this->getDetail();

        $response->assertSee('<div class="detail-form_user_comment">line one' . "\n" . 'line two &lt;b&gt;bold&lt;/b&gt; &lt;script&gt;alert(1)&lt;/script&gt;</div>', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('<br', false);
    }

    // 投稿すると、新しいコメントがいちばん上に出る
    public function testPostedCommentAppearsAtTheTop(): void
    {
        $this->comment($this->commenter('commenterName'), 'earlier comment', now()->subMinutes(5));

        $this->actingAs($this->viewer)->post('/item/comment', ['item_id' => $this->item->id, 'comment' => 'just posted'])
            ->assertStatus(302);

        $response = $this->getDetail();

        $response->assertSee('コメント(2)');
        $response->assertSeeInOrder([
            '<div class="detail-form_user_name">viewerName</div>', 'just posted',
            '<div class="detail-form_user_name">commenterName</div>', 'earlier comment',
        ], false);
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
