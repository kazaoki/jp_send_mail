<?php

use PHPUnit\Framework\TestCase;

// 初期設定
error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');
mb_language('Japanese');
mb_internal_encoding('utf-8');

// ローダー
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/helper.php';

class securityTest extends TestCase
{
    /**
     * phpable：phpable配列のキーで内部変数（$data など）を上書きできないこと（任意コード実行対策）
     * phpable に $_POST 等をそのまま渡す運用でも、評価対象のひな型が差し替わらないことを確認する
     */
    public function testPhpableCannotOverwriteInternalVars()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'subject' => 'こんにちは <?php echo $name ?> 様',
            'body'    => 'こんにちは <?php echo $name ?> 様',
            'phpable' => array(
                'name'         => '山田太郎',
                'data'         => '<?php echo "OVERWRITTEN" ?>',
                'args'         => '<?php echo "OVERWRITTEN" ?>',
                'func_phpable' => '<?php echo "OVERWRITTEN" ?>',
            ),
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['subject'], 'こんにちは 山田太郎 様');
        $this->assertContains('こんにちは 山田太郎 様', $mailed->body);
        $this->assertNotContains('OVERWRITTEN', $mailed->body);
    }

    /**
     * phpable：変数名として正しくないキー（_始まりなど）は展開されないこと
     */
    public function testPhpableSkipsInvalidKeys()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => '<?php echo isset($_secret) ? "LEAKED" : "SAFE" ?>',
            'phpable' => array(
                '_secret' => 'secret value',
            ),
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertContains('SAFE', $mailed->body);
        $this->assertNotContains('LEAKED', $mailed->body);
    }

    /**
     * 件名に改行が含まれていたら送信を止めること（ヘッダーインジェクション対策）
     */
    public function testSubjectWithNewline()
    {
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'subject' => "件名\r\nBcc: victim@example.com",
            'body'    => 'BODY SAMPLE',
        ));
        $this->assertFalse($result);
    }

    /**
     * アドレスに改行が含まれていたら送信を止めること（ヘッダーインジェクション対策）
     */
    public function testAddressWithNewline()
    {
        $result = jp_send_mail(array(
            'to'      => "to@example.com\r\nBcc: victim@example.com",
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
        ));
        $this->assertFalse($result);
    }

    /**
     * 表示名（ラベル）に改行が含まれていたら送信を止めること（ヘッダーインジェクション対策）
     */
    public function testLabelWithNewline()
    {
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => "送信者\r\nBcc: victim@example.com <from@example.com>",
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
        ));
        $this->assertFalse($result);
    }

    /**
     * アドレスに空白が含まれていたら送信を止めること（-f 経由の sendmail 引数インジェクション対策）
     */
    public function testAddressWithSpace()
    {
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'attacker -X/tmp/jp_send_mail.log @example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
        ));
        $this->assertFalse($result);
    }

    /**
     * f にメールアドレス以外の文字列が指定されていたら送信を止めること（sendmail 引数インジェクション対策）
     */
    public function testInvalidF()
    {
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'f'       => 'foo@example.com -X /tmp/jp_send_mail.log',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
        ));
        $this->assertFalse($result);
    }

    /**
     * 追加ヘッダーに改行が含まれていたら送信を止めること（ヘッダーインジェクション対策）
     */
    public function testHeaderWithNewline()
    {
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-Test' => "1\r\nBcc: victim@example.com"),
        ));
        $this->assertFalse($result);
    }

    /**
     * @ を含まないなど、メールアドレスの形式になっていないアドレスは送信を止めること
     */
    public function testAddressWithoutAtSign()
    {
        $result = jp_send_mail(array(
            'to'      => 'xexample.com',
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
        ));
        $this->assertFalse($result);
    }
}
