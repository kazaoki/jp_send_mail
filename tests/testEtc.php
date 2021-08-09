<?php

use PHPUnit\Framework\TestCase;

// 初期設定
error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');
mb_language('Japanese');
mb_internal_encoding('utf-8');

// ローダー
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../jp_send_mail.php';
require_once __DIR__.'/helper.php';

class etcTest extends TestCase
{
    /**
     * メールアドレスに空配列が指定されていたら無視するよう
     */
    public function testEmptyArray()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'cc'      => array(),
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * メールアドレスに空文字が指定されていたら無視するよう
     */
    public function testEmptyString()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'cc'      => '',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * ラベル無しメールアドレスが間違っていたらfalseで返るよう
     */
    public function testMissAddressWithoutLabel()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.comp',
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertFalse($result);
    }

    /**
     * ラベルありメールアドレスが間違っていたらfalseで返るよう
     */
    public function testMissAddressWithLabel()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'まちがいめーる <to@example.comp>',
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertFalse($result);
    }

    /**
     * カンタローブログに掲載してるサンプル01
     */
    public function testBlogSample01()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));

        $result = jp_send_mail(array(
            'to'      => '㈱あてさき様 <to@mail.com>',
            'from'    => 'おくりもと <from@mail.com>',
            'subject' => 'お問い合わせありがとうございました。',
            'body'    => file_get_contents(__DIR__.'/blog-sample/mail-thanks.php'),
            'files'   => array('スケジュール①.pdf'=>__DIR__.'/blog-sample/sample-001.pdf'),
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], '㈱あてさき様 <to@mail.com>');
        $this->assertEquals($mailed->headers['from'], 'おくりもと <from@mail.com>');
        $this->assertEquals($mailed->headers['subject'], 'お問い合わせありがとうございました。');

        // 本文パート
        $this->assertContains('サンクス！⑳', $mailed->parts[0]->body);

        // 添付１パート
        $file1 = $mailed->parts[1];
        $this->assertEquals($file1->ctype_primary, 'application');
        $this->assertEquals($file1->ctype_secondary, 'octet-stream');
        $this->assertEquals(mb_convert_encoding($file1->ctype_parameters['name'], 'utf-8', 'ISO-2022-JP-MS'), 'スケジュール①.pdf');
        $this->assertEquals(strlen($file1->body), 34034);
    }

    /**
     * カンタローブログに掲載してるサンプル02：複数のTO, CC, BCCを指定したい。
     */
    public function testBlogSample02()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));

        $result = jp_send_mail(array(
            'from'    => 'from@example.com',
            'to'      => array(
                '山田太郎 <tarou@example.com>',
                'to@example.com',
            ),
            'cc'      => array(
                '山田花子 <hanako@example.com>',
                'cc@example.com',
            ),
            'bcc'     => array(
                '山田次郎 <jirou@example.com>',
                'bcc@example.com',
            ),
            'subject' => '件名',
            'body'    => '本文',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], '山田太郎 <tarou@example.com>, to@example.com');
        $this->assertEquals($mailed->headers['cc'], '山田花子 <hanako@example.com>, cc@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['subject'], '件名');
        $this->assertContains('本文', $mailed->body);
    }

    /**
     * カンタローブログに掲載してるサンプル03：Reply-toを指定したい
     */
    public function testBlogSample03()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));

        $result = jp_send_mail(array(
            'from'    => 'from@example.com',
            'to'      => 'to@example.com',
            'reply'   => 'reply@example.com',
            'subject' => '件名',
            'body'    => '本文',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['reply-to'], 'reply@example.com');
        $this->assertEquals($mailed->headers['subject'], '件名');
        $this->assertContains('本文', $mailed->body);
    }

    /**
     * カンタローブログに掲載してるサンプル04：エンコードを UTF-8 で送りたい
     */
    public function testBlogSample04()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));

        $result = jp_send_mail(array(
            'encoding' => 'UTF-8',
            'from'     => '😃 <from@example.com>',
            'to'       => '🐶 <to@example.com>',
            'subject'  => '件😺名',
            'body'     => '本🐴文',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key, 'UTF-8');
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], '🐶 <to@example.com>');
        $this->assertEquals($mailed->headers['from'], '😃 <from@example.com>');
        $this->assertEquals($mailed->headers['subject'], '件😺名');
        $this->assertContains('本🐴文', $mailed->body);
    }

    /**
     * カンタローブログに掲載してるサンプル05：メール文章に変数を差し込みたい
     */
    public function testBlogSample05()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));

        $type = 1; // タイプが入る（1:お問い合わせ  2:資料請求）
        $name = '山田太郎'; // お名前が入る
        $result = jp_send_mail(array(
            'from'    => 'from@example.com',
            'to'      => 'to@example.com',
            'subject' => '<?php echo $type===1 ? "お問い合わせ" : "資料請求" ?>ありがとうございます。', // php5.3なのでちょい改変。
            'body'    => 'こんにちは <?php echo $name ?> 様',
            'phpable' => compact('name', 'type'), // 設定値をPHPとして実行する
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['subject'], 'お問い合わせありがとうございます。');
        $this->assertContains('こんにちは 山田太郎 様', $mailed->body);
    }

    /**
     * カンタローブログに掲載してるサンプル06：緊急度高いメールヘッダー付けたい
     */
    public function testBlogSample06()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'from'    => 'from@example.com',
            'to'      => 'to@example.com',
            'subject' => '件名',
            'body'    => '本文',
            'headers' => array(
                'Priority'      => 'urgent', // 緊急度高い設定
                'X-MailDev-Key' => $maildev_key
            ),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['subject'], '件名');
        $this->assertEquals($mailed->headers['priority'], 'urgent');
        $this->assertContains('本文', $mailed->body);
    }

    /**
     * カンタローブログに掲載してるサンプル07：ファイルを添付したい
     */
    public function testBlogSample07()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'from'    => 'from@example.com',
            'to'      => 'to@example.com',
            'subject' => '件名',
            'body'    => '本文',
            'files'   => array(
                __DIR__.'/blog-sample/cat.jpg', // 「cat.jpg」として添付される
                'いぬ.jpg' => __DIR__.'/blog-sample/dog.jpg', // 「いぬ.jpg」として添付される
            ),
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'to@example.com');
        $this->assertEquals($mailed->headers['from'], 'from@example.com');
        $this->assertEquals($mailed->headers['subject'], '件名');

        // 本文パート
        $this->assertContains('本文', $mailed->parts[0]->body);

        // 添付１パート
        $file1 = $mailed->parts[1];
        $this->assertEquals($file1->ctype_primary, 'application');
        $this->assertEquals($file1->ctype_secondary, 'octet-stream');
        $this->assertEquals(mb_convert_encoding($file1->ctype_parameters['name'], 'utf-8', 'ISO-2022-JP-MS'), 'cat.jpg');
        $this->assertEquals(strlen($file1->body), 55589);

        // 添付２パート
        $file2 = $mailed->parts[2];
        $this->assertEquals($file2->ctype_primary, 'application');
        $this->assertEquals($file2->ctype_secondary, 'octet-stream');
        $this->assertEquals(mb_convert_encoding($file2->ctype_parameters['name'], 'utf-8', 'ISO-2022-JP-MS'), 'いぬ.jpg');
        $this->assertEquals(strlen($file2->body), 161314);
    }
}
