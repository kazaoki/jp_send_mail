<?php

use PHPUnit\Framework\TestCase;

// 初期設定
error_reporting(E_ALL);
ini_set('error_log', '/var/log/php/error.log');
mb_language('Japanese');
mb_internal_encoding('utf-8');

// ローダー
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../jp_send_mail.php';
require __DIR__.'/helper.php';

class MessageTest extends TestCase
{
    /**
     * toにラベルなしのメールアドレスをセットした場合のテスト
     */
    public function testToNormal()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@kazaoki.jp',
            'from'    => 'from@kazaoki.jp',
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
        $this->assertEquals($mailed->headers['to'], 'to@kazaoki.jp');
        $this->assertEquals($mailed->headers['from'], 'from@kazaoki.jp');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * toにラベルありのメールアドレスをセットした場合のテスト（ASCIIラベル）
     */
    public function testToWithLabel()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'test man <to@kazaoki.jp>',
            'from'    => 'from@kazaoki.jp',
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
        $this->assertEquals($mailed->headers['to'], 'test man <to@kazaoki.jp>');
        $this->assertEquals($mailed->headers['from'], 'from@kazaoki.jp');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }

    /**
     * toにラベルありのメールアドレスをセットした場合のテスト（日本語ラベル）
     */
    public function testToWithLabelByMultibyte()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'テストマン <to@kazaoki.jp>',
            'from'    => 'from@kazaoki.jp',
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
        $this->assertEquals($mailed->headers['to'], 'テストマン <to@kazaoki.jp>');
        $this->assertEquals($mailed->headers['from'], 'from@kazaoki.jp');
        $this->assertEquals($mailed->headers['subject'], 'SUBJECT SAMPLE');
        $this->assertContains('BODY SAMPLE', $mailed->body);
    }
}
