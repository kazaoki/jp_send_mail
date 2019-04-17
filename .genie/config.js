
const config = module.exports.config = {}; // この行は削除や変更をしないように！

/**
 * genie設定ファイル
 * =============================================================================
 * - external_port系の設定はポート番号以外に'auto'が使用可能です。割り当てられたポートが環境変数に入ってきます。
 */

/**
 * genie本体設定
 * -----------------------------------------------------------------------------
 */
config.core =
{
	// 使用する Docker イメージ情報
	docker: {
		image: 'kazaoki/genie:node',
		// image: 'kazaoki/genie:node',
		// machine: 'sandbox',
		// name: 'genie-xxx',
		name: 'genie-kazaoki-jp-send-mail',
		// options: '--cpuset-cpus=0-1',
		hosts: [
			// 'genie-test.com:127.0.0.1',
		],
		// ip_force: '192.168.99.100',
		mounts: [ // ホスト側(左側)を/以外で始めるとホームパスからの指定になります。
			// 'app:/app',
			// 'home-data:/home/xxx/',
			'./:/jp_send_mail/',
		],
		mount_mode: 'share',
		// network: 'my_docker_nw',
		down_with_volumes: false, // down時に、関連するVolumeも一緒に削除するかどうか（lockedも消えます）
	},

	// up時/down時のメッセージ表示
	memo: {
		// up: [
		// 	{info: '起動します。'},
		// 	{warning: '開発版です'},
		// ],
		// down: [{success: '終了します。'}],
	},

	// 音声スピーチの有効/無効
	enable_say: true,
}

/**
 * 言語設定
 * -----------------------------------------------------------------------------
 */
config.lang =
{
	// PHP設定
	php: {
		// ↓バージョンを指定する場合は 'kazaoki/phpenv:' にTagNameをつなげてください → https://hub.docker.com/r/kazaoki/phpenv/tags/
		phpenv_image: 'kazaoki/phpenv:5.3.3',
		error_report: process.env['GENIE_RUNMODE']==='develop',
		timezone: 'Asia/Tokyo',
		xdebug: {
			host: '192.168.0.10',
			port: 9000,
 		}
	},
}

/**
 * ログ設定
 * -----------------------------------------------------------------------------
 */
config.log =
{
	// ファイルログ監視
	// 複数のログファイルをマージして監視できます。一次元目の配列を複数記述すると横に分割していきます。
	// それぞれ色も指定可能です → red, green, yellow, blue, magenta, cyan, white(default)
	tail: [
		[
			['/var/log/php/error.log', 'red'],
			// ['/var/log/httpd/access_log', 'white'],
			// ['/var/log/httpd/error_log', 'red'],
			// ['/var/log/httpd/ssl_access_log', 'white'],
			// ['/var/log/httpd/ssl_request_log', 'white'],
			// ['/var/log/httpd/ssl_error_log', 'red'],
		],
		// [
		// 	'/var/log/nginx/access.log',
		// 	['/var/log/nginx/error.log', 'red'],
		// ],
	],

	// Fluentd設定（サービスログ）
	fluentd: {
		config_file: '/etc/td-agent/td-agent.conf',
	},
}

/**
 * http設定
 * -----------------------------------------------------------------------------
 */
config.http =
{
	// ブラウザで開く設定
	browser: {
		apps: ['chrome'], // chrome|firefox|ie|safari|opera 未指定は既定のブラウザで開く。複数指定可。
		// apps: process.platform==='win32'
		// 	? ['chrome', 'ie'] // for Windows
		// 	: ['chrome', 'safari'] // for macOS
		// ,
		schema: 'http',
		path: '/',
		// at_upped: true, // UP時にブラウザオープンするか
	},

	// Apache設定
	apache: {
		// enabled: true,
		public_dir: 'public_html',
		no_log_regex: '\.(gif|jpg|jpeg|jpe|png|css|js|ico)$',
		real_ip_log_enabled: false,
		external_http_port: 80,
		external_https_port: 443,
	},

	// Nginx設定
	// nginx: {
	// 	enabled: true,
	// 	public_dir: 'public_html',
	// 	external_http_port: 80,
	// 	external_https_port: 443,
	// },

	// ngrok設定
	ngrok: {
		args: 'http 80',
		authtoken: '',
		subdomain: '',
		basic_user: '',
		basic_pass: '',
	},
}

/**
 * DB設定（DB名ごとに別コンテナとして起動します）
 * -----------------------------------------------------------------------------
 */
config.db =
{
	// MySQL設定
	mysql: {
		// main: {
		// 	repository : 'mysql:5.5',
		// 	host       : 'main.mysql-server',
		// 	name       : 'sample_db',
		// 	user       : 'sample_user',
		// 	pass       : '123456789',
		// 	charset    : 'utf8mb4',
		// 	collation  : 'utf8mb4_unicode_ci',
		// 	dump_genel : 3,
		// 	// volume_lock: true,
		// 	external_port: 3306,
		// },
	},

	// PostgreSQL設定
	// ※locale には ja_JP.UTF-8 | ja_JP.EUC-JP が指定可能で、encoding はこれにより自動的に設定されます。
	postgresql: {
		// main: {
		// 	repository : 'postgres:9.4',
		// 	host       : 'main.postgresql-server',
		// 	name       : 'sample_db',
		// 	user       : 'sample_user',
		// 	pass       : '123456789',
		// 	locale     : 'ja_JP.UTF-8',
		// 	dump_genel : 3,
		// 	// volume_lock: true,
		// 	external_port: 5432,
		// },
	},
}

/**
 * メール設定
 * -----------------------------------------------------------------------------
 */
config.mail =
{
	// Postfix設定
	postfix: {
		enabled: true, // maildevを有効にする場合もtrueにしてください。
		// force_envelope: 'test@xx.xx',
	},

	// MailDev設定
	maildev: {
		enabled: true,
		external_port: 9981,
		// option_string: ''
	},
}

/**
 * データ転送設定
 * -----------------------------------------------------------------------------
 */
config.trans =
{
	// dlsync設定
	dlsync: {
		remote_host: '', // ポート指定したい場合は `(ホスト):(ポート)` のように指定可能です。
		remote_user: '',
		remote_pass: '',
		remote_dir: '/public_html',
		local_dir: 'public_html', // ホームパスからの相対です
		remote_charset: 'utf8', // utf8, sjis 等
		local_charset: 'utf8',
		lftp_option: [
			'-a',
			'--verbose',
			'--delete',
			'--parallel=1', // 2や3とかにすれば速いけど、FTPサーバによっては接続拒否されてしまうかもしれないので注意。
			// '--log=/genie/lftp_cmd.log',
			'-X', '.genie',
			// '-X', 'media/*',
			// '-X', 'assets/font-awesome/*',
		].join(' '),
	},

	// sshd設定
	sshd: {
		// enabled: true,
		login_user: 'genie',
		login_pass: '123456789',
		login_path: '/mnt/host',
		external_port: 'auto',
	},
}

/**
 * テストスクリプト設定
 * -----------------------------------------------------------------------------
 * `genie test` でテスト専用コンテナが起動してから実行されます。
 * 完了後、そのコンテナは自動削除されます。
 */
config.test =
{
	// テスト前に実行するコマンド
	// before: '',

	// テストを実行するコマンド
	run: 'npx mocha --reporter mochawesome --recursive tests --reporter-options reportDir=tests-report/mochawesome-report/,quiet=true',

	// テスト後に実行するコマンド
	after: 'g open --report',
}

/**
 * カスタムコマンド追加
 * -----------------------------------------------------------------------------
 * コンテナ内で実行される
 */
config.command =
{
	top: 'top',
	ngrok: 'ngrok http 80',
	phplist: '. ~/.bashrc && phpenv install --list',
	xoff: '/opt/misc/php-xdebug-off.sh',
	xon: '/opt/misc/php-xdebug-on.sh',
	composer: 'cd /var/www/html/slim/ && /root/.anyenv/envs/phpenv/shims/composer install',
	autoload: 'cd /var/www/html/slim/ && /root/.anyenv/envs/phpenv/shims/composer dump-autoload',
	phpunit: '/root/.anyenv/envs/phpenv/shims/php /jp_send_mail/vendor/bin/phpunit -c /jp_send_mail/phpunit.xml',
	phpunitcv: '/root/.anyenv/envs/phpenv/shims/php /jp_send_mail/vendor/bin/phpunit -c /jp_send_mail/phpunit.xml --coverage-html /jp_send_mail/tests/coverage-html',
}
