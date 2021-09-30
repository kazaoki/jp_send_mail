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

class hankakuKana extends TestCase
{
    /**
     * エンコーディングがUTF-8ではない場合、半角カタカナが全角カタカナに変換されているか
     */
    public function testDefault()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <to@example.com>',
            'from'    => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <from@example.com>',
            'subject' => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ｻﾌﾞｼﾞｪｸﾄ',
            'body'    => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ﾃｽﾄﾎﾝﾌﾞﾝ ﾊﾟﾋﾟﾌﾟﾍﾟﾎﾟ',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
            // 'encoding'=> 'UTF-8',
            // 'force_hankana'=> true,
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'ハンカクカタカナダヨ <to@example.com>');
        $this->assertEquals($mailed->headers['from'], 'ハンカクカタカナダヨ <from@example.com>');
        $this->assertEquals($mailed->headers['subject'], 'ハンカクカタカナダヨ サブジェクト');
        $this->assertEquals($mailed->body, "\r\nハンカクカタカナダヨ テストホンブン パピプペポ\r\n");
    }

    /**
     * エンコーディングがUTF-8ではない場合、force_hankanaをtrueに指定することで半角カタカナのまま送信できるか
     */
    public function testForceHankakuKana()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <to@example.com>',
            'from'    => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <from@example.com>',
            'subject' => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ｻﾌﾞｼﾞｪｸﾄ',
            'body'    => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ﾃｽﾄﾎﾝﾌﾞﾝ ﾊﾟﾋﾟﾌﾟﾍﾟﾎﾟ',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
            // 'encoding'=> 'UTF-8',
            'force_hankana'=> true,
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <to@example.com>');
        $this->assertEquals($mailed->headers['from'], 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <from@example.com>');
        $this->assertEquals($mailed->headers['subject'], 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ｻﾌﾞｼﾞｪｸﾄ');
        $this->assertEquals($mailed->body, "\r\nﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ﾃｽﾄﾎﾝﾌﾞﾝ ﾊﾟﾋﾟﾌﾟﾍﾟﾎﾟ\r\n");
    }

    /**
     * エンコーディングがUTF-8なら、force_hankanaの指定は関係なく、半角カタカナのまま送信
     */
    public function testUtf8()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <to@example.com>',
            'from'    => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <from@example.com>',
            'subject' => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ｻﾌﾞｼﾞｪｸﾄ',
            'body'    => 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ﾃｽﾄﾎﾝﾌﾞﾝ ﾊﾟﾋﾟﾌﾟﾍﾟﾎﾟ',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
            'encoding'=> 'UTF-8',
            // 'force_hankana'=> true,
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key, 'utf-8');
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <to@example.com>');
        $this->assertEquals($mailed->headers['from'], 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ <from@example.com>');
        $this->assertEquals($mailed->headers['subject'], 'ﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ｻﾌﾞｼﾞｪｸﾄ');
        $this->assertEquals($mailed->body, "\r\nﾊﾝｶｸｶﾀｶﾅﾀﾞﾖ ﾃｽﾄﾎﾝﾌﾞﾝ ﾊﾟﾋﾟﾌﾟﾍﾟﾎﾟ\r\n");
    }
}
