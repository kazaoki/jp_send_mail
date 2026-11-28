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

// SMTPの接続先は maildev 自身（maildev は SMTP サーバーとして受け取る）。
// maildev の SMTP ポートが 1025 でない環境では、ここの値を合わせること。
define('JP_SMTP_TEST_HOST', '127.0.0.1');
define('JP_SMTP_TEST_PORT', 1025);

class SmtpTest extends TestCase
{
    /**
     * SMTPで通常送信した場合のテスト
     */
    public function testSmtpNormal()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
            'smtp'    => array('host'=>JP_SMTP_TEST_HOST, 'port'=>JP_SMTP_TEST_PORT),
        ));
        $this->assertTrue($result);

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
     * SMTP送信時、Bccヘッダーが送信データに含まれないことのテスト
     * （Bcc の宛先へはエンベロープでのみ届き、ヘッダーには現れない）
     */
    public function testSmtpBccNotInHeaders()
    {
        // メール送信
        $maildev_key = md5(uniqid(rand(),1));
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'cc'      => 'cc@example.com',
            'bcc'     => 'bcc@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'headers' => array('X-MailDev-Key'=>$maildev_key),
            'smtp'    => array('host'=>JP_SMTP_TEST_HOST, 'port'=>JP_SMTP_TEST_PORT),
        ));
        $this->assertTrue($result);

        // 配信されるまでちょっと待つ。
        msleep(300);

        // 実際に配信されたメールの中身チェック
        $mailed = mail_get_contents($maildev_key);
        $this->assertNotFalse($mailed);
        $this->assertEquals($mailed->headers['cc'], 'cc@example.com');
        $this->assertEmpty(@$mailed->headers['bcc']);
    }

    /**
     * 接続できないSMTPサーバーを指定したら false が返るテスト
     */
    public function testSmtpUnreachable()
    {
        $result = jp_send_mail(array(
            'to'      => 'to@example.com',
            'from'    => 'from@example.com',
            'subject' => 'SUBJECT SAMPLE',
            'body'    => 'BODY SAMPLE',
            'smtp'    => array('host'=>'127.0.0.1', 'port'=>59999, 'timeout'=>3),
        ));
        $this->assertFalse($result);
    }
}
