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

class ReplyTest extends TestCase
{
    /**
     * replyにラベルなしのメールアドレスをセットした場合のテスト
     */
    public function testReplyNormal()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'reply'   => 'reply@example.com',
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
        $this->assertEquals($mailed->headers['reply-to'], 'reply@example.com');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * replyにラベルありのメールアドレスをセットした場合のテスト（ASCIIラベル）
     */
    public function testReplyWithLabel()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'reply'   => 'test man <reply@example.com>',
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
        $this->assertEquals($mailed->headers['reply-to'], 'test man <reply@example.com>');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * replyにラベルありのメールアドレスをセットした場合のテスト（日本語ラベル）
     */
    public function testReplyWithLabelByMultibyte()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'reply'   => 'テストマン <reply@example.com>',
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
        $this->assertEquals($mailed->headers['reply-to'], 'テストマン <reply@example.com>');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * replyにラベルありのメールアドレスをセットした場合のテスト（日本語ラベル）（丸数字などのMS文字）
     */
    public function testReplyWithLabelByMultibytePlusMS()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'reply'   => '㈱テストマン① <reply@example.com>',
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
        $this->assertEquals($mailed->headers['reply-to'], '㈱テストマン① <reply@example.com>');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * replyにカンマ区切りで複数指定
     */
    public function testReplyAnyWithComma()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'reply'   => array('㈱テストマン① <reply@example.com>', '★ <reply2@example.com>'),
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertFalse($result);
    }
}
