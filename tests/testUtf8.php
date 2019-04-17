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

class utf8Test extends TestCase
{
    /**
     * UTF-8メール配信テスト
     */
    public function testUtf8()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => '日本語 <to@kazaoki.jp>',
            'from'    => '日本語 <from@kazaoki.jp>',
            'subject' => '日本語 SUBJECT SAMPLE',
            'body'    => '日本語 BODY SAMPLE',
            'encoding'=> 'utf-8',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
        ));
        $this->assertNotFalse($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key, 'utf-8');
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['to'], '日本語 <to@kazaoki.jp>');
        $this->assertEquals($mailed->headers['from'], '日本語 <from@kazaoki.jp>');
        $this->assertEquals($mailed->headers['subject'], '日本語 SUBJECT SAMPLE');
        $this->assertContains('日本語 BODY SAMPLE', $mailed->body);
    }
}
