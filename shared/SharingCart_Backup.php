<?php
/**
 *  SharingCart_Backup
 */

require_once dirname(__FILE__).'/SharingCart_BackupRestoreBase.php';

// require_once $CFG->dirroot.'/backup/backuplib.php';
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once $CFG->libdir.'/blocklib.php';

class SharingCart_Backup extends SharingCart_BackupRestoreBase
{
    protected $contextid;
    protected $fileid;

	/* implements */ protected function requireCapabilities($course_id)
	{
		$context = get_context_instance(CONTEXT_COURSE, $course_id);
		// require_capability('moodle/site:backup', $context);
	}
	/* implements */ protected function & createPreferences()
	{
		// バックアップに必要なデータを保持するオブジェクト
		// Moodle コアがグローバル変数として参照するので変数名は $preferences 固定
		// (backup_encode_absolute_links() @ /backup/backuplib.php)
		$GLOBALS['preferences'] = new stdClass;
		
		return $GLOBALS['preferences'];
	}

	protected $cm_id;
	
	// スラッシュのエスケープ記号
	const SLASH_ESC = '$@SLASH@$';
	
	// zip に一緒にアーカイブするデータおよびファイル
	protected $zip_userdata;
	protected $zip_userfile;
	
	// (進捗以外の)メッセージが出力されたかどうかのフラグ
	protected $message_output;
	
	public function __construct($course_id, $section_i)
	{
		parent::__construct($course_id, $section_i);
		
		$this->message_output = FALSE;
		
		// 初期値設定
		// true|false ではなく 1|0 で指定
		// 2 以上のマジックナンバーはコメント参照 (無いものは未調査)
		$this->prefs->backup_course            = $this->course->id;
		$this->prefs->backup_section           = $this->section->id;
		$this->prefs->backup_metacourse        = 1;
		$this->prefs->backup_users             = 2; // (0:全て, 1:必要な分だけ, 2:なし)
		$this->prefs->backup_logs              = 0;
		$this->prefs->backup_user_files        = 0;
		$this->prefs->backup_course_files      = 1;
		$this->prefs->backup_site_files        = 0;
		$this->prefs->backup_gradebook_history = 0;
		$this->prefs->backup_messages          = 0;
		$this->prefs->backup_blogs             = 0;
		$this->prefs->newdirectoryname         = NULL;
		
		$this->zip_userdata = array();
		$this->zip_userfile = array();
	}
	
	public function setSilent()
	{
		// コアライブラリがHTMLを出力しないようにサイレントモードにする
		define('BACKUP_SILENTLY', TRUE);
		
		$GLOBALS['CFG']->debug = FALSE;
	}
	
	public function beginPreferences()
	{
	  //$this->debug->trace();
		
		// バックアップ作成先ディレクトリの初期値としてユーザディレクトリをセット (存在しなければここで作成)
	  //	$userdir = make_user_directory($GLOBALS['USER']->id);
	  //	if (!$userdir)
	  //		throw SharingCart_Exception('User directory creation failure');
	  //	$this->setZipDir($userdir);
	}
	
	public function endPreferences()
	{
		// Moodle 環境変数(バージョンなど)をセット
	  //backup_add_static_preferences($this->prefs);
		
		$this->debug->setVerticalDepth(1);
		$this->debug->trace($this->prefs);
		$this->debug->setVerticalDepth(0);
	}
	
	public function setZipDir($dir)
	{
		$this->prefs->backup_destination = $dir;
	}
	
	public function setZipName($name)
	{
		$this->prefs->backup_name = $name;
	}
	
	public function addModule($module, $cm_id)
	{
	  $this->debug->trace();
	  error_log("addModule ".$cm_id);
		$this->cm_id = $cm_id;
		// モジュール固有のバックアップ関数
		//		$fn_backup       = $module->name.'_backup_mods';
		//		$fn_backup_one   = $module->name.'_backup_one_mod';
		//		$fn_check_backup = $module->name.'_check_backup_mods';
		
		//		if (!$this->getParam('exists_'.$module->name)) {
			// モジュール固有のバックアップライブラリが存在するかチェック
		//			global $CFG;
		//			$backuplib = $CFG->dirroot.'/mod/'.$module->name.'/backuplib.php';
		//			if (!is_file($backuplib))
		//				throw new SharingCart_ModuleException('Not supported module: '.$module->name);
		//			require_once $backuplib;
			
			// 必要な関数を実装しているかチェック
		//	if (!function_exists($fn_backup) || !function_exists($fn_check_backup))
		//		throw new SharingCart_ModuleException('Not supported module: '.$module->name);
			
			$this->setParam('exists_'.$module->name, TRUE);
			//		}
		
		// バックアップリストにモジュールを追加
		if (empty($this->prefs->mods)) {
			$this->prefs->mods = array();
		}
		if (empty($this->prefs->mods[$module->name])) {
			$this->prefs->mods[$module->name] = new stdClass;
			$this->prefs->mods[$module->name]->name = $module->name;
			
			$this->setParam('backup_'.$module->name, TRUE);
			$this->prefs->mods[$module->name]->backup = TRUE;
			
			$this->setParam('backup_user_info_'.$module->name, FALSE);
			$this->prefs->mods[$module->name]->userinfo = FALSE;
		}
		
		// インスタンスが存在し、かつ個別に(インスタンスレベルで)バックアップ可能かチェック
		//		global $DB;
		//		if ($DB->count_records('course_modules', array('course'=> $this->course->id), 'module', $module->id) != 0
		//					&& function_exists($fn_backup_one)) {
		//			$this->setParam('exists_one_'.$module->name, TRUE);
			
			// コースモジュールインスタンスを全て列挙してキャッシュする
		//			static $instances = array();
		//			if (empty($instances)) {
		//				$instances = get_all_instances_in_course($module->name, $this->course, NULL, TRUE);
				// あらかじめセクション番号で絞り込む
		//				$instances = array_filter($instances, create_function('$inst', '
		//					return $inst->section == '.$this->section->section.';
		//				'));
		//			}
			
			// コースモジュールIDで検索
		//			$key = reset(array_keys(array_filter($instances, create_function('$inst', '
		//				return $inst->coursemodule == '.$cm_id.';
		//			'))));
		//			if ($key === FALSE) // 存在しない
		//				throw new SharingCart_ModuleException('Invalid ID');
			
		//			$instance = $instances[$key];
		//			unset($instances[$key]); // 次回の配列検索を高速化するため使用済みのキーを削除
			
			// バックアップリストにモジュールインスタンスを追加
		//	if (empty($this->prefs->mods[$module->name]->instances)) {
		//				$this->prefs->mods[$module->name]->instances = array();
		//			}
		//			if (empty($this->prefs->mods[$module->name]->instances[$instance->id])) {
		//				$this->prefs->mods[$module->name]->instances[$instance->id] = new stdClass;
		//				$this->prefs->mods[$module->name]->instances[$instance->id]->name = $instance->name;
				
		//				$this->setParam('backup_'.$module->name.'_instance_'.$instance->id, TRUE);
		//				$this->prefs->mods[$module->name]->instances[$instance->id]->backup = TRUE;
				
		//				$this->setParam('backup_user_info_'.$module->name.'_instance_'.$instance->id, FALSE);
		//				$this->prefs->mods[$module->name]->instances[$instance->id]->userinfo = FALSE;
		//			}
			
			// we need this later to determine what to display in modcheckbackup.
		//	$this->setParam('backup_'.$module->name.'_instances', 1);
			
		//	$registered_instances = $this->getParam($module->name.'_instances');
		//			if (empty($registered_instances)) {
		//				$registered_instances = array();
				//	}
		//	$registered_instances += array($key => $instance);
		//			$this->setParam($module->name.'_instances', $registered_instances);
			
			// モジュール固有のチェック関数を通す
		//	$fn_check_backup($this->course->id, FALSE/*user_data*/, $this->getUnique(),
		//		array($key => $instance)
		//			);
		//		}
	}
	
	public function addUserData($filename /*"user.xml"*/, $data)
	{
		$this->zip_userdata[$filename] = $data;
	}
	public function addUserFile($filename /*"user.bin"*/, $path)
	{
		$this->zip_userfile[$filename] = $path;
	}

	public function getFileID()
	{
	    return $this->fileid;
	}	

	public function getContextID()
	{
	    return $this->contextid;
	}	

	
	public function execute()
	{
	  global $USER;
	  $bc = new backup_controller(backup::TYPE_1ACTIVITY, $this->cm_id, backup::FORMAT_MOODLE,	backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id);
	  $bc->execute_plan();
	  $results = $bc->get_results();
	  $file = $results["backup_destination"];
	  $this->contextid = $file->get_contextid();
	  $this->fileid = $file->get_id();
	  $bc->destroy();
	  
	  //		$this->debug->trace();
		
	  //	$this->prepareDir();
		
	  //	$this->createXml();
		
	  //	$temp_dir = $this->getTempDir().'/';
		
		// moodle.xml 内を検索し、本体未対応や取りこぼしたリンクを
		// 独自形式に書き換え、バックアップ一時ディレクトリへコピー
	  //	$xml = file_get_contents($temp_dir.'moodle.xml');
	  //	$xml = $this->encodeLinksAndBackupTargets($xml);
	  //	file_put_contents($temp_dir.'moodle.xml', $xml);
		
		// ユーザ指定のデータおよびファイルをZIPに追加
	  //	foreach ($this->zip_userdata as $filename => $data) {
	  //		file_put_contents($temp_dir.$filename, $data);
	  //	}
	  //	foreach ($this->zip_userfile as $filename => $path) {
	  //		copy($path, $temp_dir.$filename);
	  //	}
		
	  //	$this->createZip();
		
	  //	$this->cleanupDir();
		
	  //	if (defined('BACKUP_SILENTLY')) {
	  //		if (empty($this->message_output)) {
	  //			if (is_callable('header_remove', FALSE, $header_remove)) {
					// 可能であれば出力済みのHTTPヘッダを除去し、
					// 直接リダイレクトできる状態にする (PHP 5.3.x)
	  //				$header_remove();
	  //				}
				// (進捗以外の)メッセージが出力されていなければ、成功フラグを立てる
	//			$this->execute_succeeded = TRUE;
	//		}
	//	} else {
			// 非サイレントモードでは成功かどうかの判定が困難なのでHTTPヘッダで簡易判定する
	//		if (!headers_sent()) {
	//			$this->execute_succeeded = TRUE;
	//		}
	//		}
	  $this->execute_succeeded = TRUE;
	}
	
	protected function createZip()
	{
		// ZIP作成
		if (!backup_zip($this->prefs))
			throw new SharingCart_Exception('Zip creation failure');
		
		$source = $GLOBALS['CFG']->dataroot.'/temp/backup/'.$this->prefs->backup_unique_code.'/'.$this->prefs->backup_name;
		$target = $this->prefs->backup_destination.'/'.$this->prefs->backup_name;
		
		if (!is_file($source))
			throw new SharingCart_Exception('Zip creation failure - The specified external zip program seems not to be working');
		
		// データディレクトリにコピー
		if (!copy_zip_to_course_dir($this->prefs)) {
			//throw new SharingCart_Exception('Zip creation failure');
			
			// Moodle関数が失敗したら自前でコピーを試みる
			if (!SharingCart_FileSystem::copy($source, $target,
			                                  SharingCart_FileSystem::OVERWRITE | SharingCart_FileSystem::NEWERONLY))
				throw new SharingCart_Exception('File copying failure ("'.$source.'" -> "'.$target.'")');
			
			@chmod($target, $GLOBALS['CFG']->directorypermissions);
		}
	}
	
	protected function createXml()
	{
		$this->debug->trace();
		
		// サイレントモードでは進捗の出力を抑制
		if (defined('BACKUP_SILENTLY')) {
			ob_start(array($this, 'suppressProgress'));
		}
		
		// moodle.xml 作成
		$xml = backup_open_xml($this->getUnique());
		if (!$xml)
			throw new SharingCart_XmlException('XML open failure');
		
		if (!backup_general_info($xml, $this->prefs))
			throw new SharingCart_XmlException('general info');
		
		$this->xmlSetCourse($xml);
		
		if (!backup_close_xml($xml))
			throw new SharingCart_XmlException('XML close failure');
		
		if (defined('BACKUP_SILENTLY')) {
			$output = ob_get_contents();
			ob_end_clean();
			if (preg_match('@^(?:\.|\s|<br\s*/?>)*$@', $output)) {
				// 進捗表示の文字列は出力しない
			} else {
				$this->message_output = TRUE;
				echo $output;
			}
		}
	}
	protected function suppressProgress($output)
	{
		if (preg_match('@^(?:\.|\s|<br\s*/?>)*$@', $output)) {
			// 進捗表示の文字列をフィルタリングする
			return '';
		}
		$this->message_output = TRUE;
		return $output;
	}
	
	protected function xmlSetCourse($xml)
	{
		$this->debug->trace();
		
		if (!backup_course_start($xml, $this->prefs))
			throw new SharingCart_XmlException('course start');
		
		if (!empty($this->prefs->backup_metacourse)) {
			if (!backup_course_metacourse($xml, $this->prefs))
				throw new SharingCart_XmlException('course metacourse');
		}
		
		// セクション
		$this->xmlSetSection($xml);
		
		if (!backup_user_info($xml, $this->prefs))
			throw new SharingCart_XmlException('user info');
		
		// モジュール
		$this->xmlSetModules($xml);
		
		// 問題バンク
		$this->xmlSetQuestions($xml);
		
		if (!backup_scales_info($xml, $this->prefs))
			throw new SharingCart_XmlException('scales info');
		
		if (!backup_groups_info($xml, $this->prefs) ||
				!backup_groupings_info($xml, $this->prefs) ||
					!backup_groupings_groups_info($xml, $this->prefs))
			throw new SharingCart_XmlException('groups');
		
		if (!backup_events_info($xml, $this->prefs))
			throw new SharingCart_XmlException('events info');
		
		if (!self::silent_call('backup_gradebook_info', $xml, $this->prefs))
			throw new SharingCart_XmlException('gradebook info');
		
		
		if (!backup_format_data($xml, $this->prefs))
			throw new SharingCart_XmlException('format data');
		
		if (!backup_course_end($xml, $this->prefs))
			throw new SharingCart_XmlException('course end');
	}
	
	protected function xmlSetSection($xml)
	{
		$this->debug->trace();
		
		fwrite($xml, start_tag('SECTIONS', 2, TRUE));
		{
			fwrite($xml, start_tag('SECTION', 3, TRUE));
			{
				fwrite($xml, full_tag('ID'     , 4, FALSE, $this->section->id     ));
				fwrite($xml, full_tag('NUMBER' , 4, FALSE, $this->section->section));
				fwrite($xml, full_tag('SUMMARY', 4, FALSE, $this->section->summary));
				fwrite($xml, full_tag('VISIBLE', 4, FALSE, $this->section->visible));
				
				self::silent_call('backup_course_modules', $xml, $this->prefs, $this->section);
			}
			fwrite($xml, end_tag('SECTION', 3, TRUE));
		}
		fwrite($xml, end_tag('SECTIONS', 2, TRUE));
	}
	
	protected function xmlSetModules($xml)
	{
		$this->debug->trace();
		
		if (!empty($this->prefs->mods)) {
			if (!backup_modules_start($xml, $this->prefs))
				throw new SharingCart_XmlException('modules start');
			
			foreach ($this->prefs->mods as $name => $mod) {
				if (!backup_module($xml, $this->prefs, $name))
					throw new SharingCart_XmlException('module');
			}
			
			if (!backup_modules_end($xml, $this->prefs))
				throw new SharingCart_XmlException('modules end');
		}
	}
	
	protected function xmlSetQuestions($xml)
	{
		$this->debug->trace();
		
		global $CFG,$DB;
		require_once $CFG->dirroot.'/question/backuplib.php';
		
		// バックアップ対象の問題カテゴリを追加
		if (!empty($this->prefs->mods['quiz'])) {
			
			// 本当に必要としている問題を列挙しておく (後で不要な問題を除外)
			$necessary_questions = array();
			
			$categories = array();
			foreach ($this->prefs->mods['quiz']->instances as $quiz_id => $inst) {
				$quiz = $DB->get_record('quiz', 'id', $quiz_id);
				$question_ids = array_filter(explode(',', $quiz->questions));
				foreach ($question_ids as $question_id) {
					$question = $DB->get_record('question', 'id', $question_id);
					if (!$question)
						throw new SharingCart_XmlException('questions - question not found');
					// 問題使用フラグを立てる
					$necessary_questions[$question->id] = TRUE;
					
					// 問題をバックアップ予約テーブルに登録
					if (!backup_putid($this->getUnique(), 'question', $question_id, 0))
						throw new SharingCart_XmlException('questions - backup_putid() failure');
					
					// Cloze(穴埋め問題) の子問題をバックアップ予約テーブルに登録
					if ($question->qtype == 'multianswer') {
						$multianswer = $DB->get_record('question_multianswer', 'question', $question->id);
						if (!$multianswer)
							throw new SharingCart_XmlException('questions - multianswer children not found');
						
						$child_question_ids = array_filter(explode(',', $multianswer->sequence));
						foreach ($child_question_ids as $child_question_id) {
							if (!backup_putid($this->getUnique(), 'question', $child_question_id, 0))
								throw new SharingCart_XmlException('questions - multianswer backup_putid() failure');
							// 問題使用フラグを立てる
							$necessary_questions[$child_question_id] = TRUE;
						}
					}
					
					// 問題カテゴリをバックアップ予約テーブルに登録
					if (!backup_putid($this->getUnique(), 'question_categories', $question->category, 0))
						throw new SharingCart_XmlException('question categories - backup_putid() failure');
				}
			}
			
			$necessary_question_ids = array_keys($necessary_questions);
			
			// バックアップ不要な問題をバックアップ予約テーブルから除外
			execute_sql("
				DELETE FROM {$CFG->prefix}backup_ids
					WHERE backup_code = ".$this->getUnique()."
					  AND table_name  = 'question'
					  AND NOT old_id IN (".implode(',', $necessary_question_ids).")
			", FALSE);
		}
		
		if (!backup_question_categories($xml, $this->prefs))
			throw new SharingCart_XmlException('question categories');
	}
	
	/**
	 * Moodle 本体未対応のリンク書き換え＆ファイルバックアップ
	 */
	protected function encodeLinksAndBackupTargets($xml)
	{
	  global $DB;
		$xml = preg_replace_callback(
			// サイトファイル：絶対パス "http://xxx/file.php/yyy"
			// コースファイル：相対パス "../file.php/yyy"
			'@(&quot;)(?:(?!&quot;).)+?/file.php(?:\?file\=)?/(\d+)/(.+?)(&quot;)@',
			array($this, 'encodeLinksAndBackupTargets_clbk'),
			$xml
		);
		
		// Moodle コアによって $@FILEPHP@$ に置き換えられたリンク先ファイルをバックアップ
		$xml = preg_replace_callback(
			'@(&quot;)'.self::preg_quote_atmark(parent::LINK_FILE_ESC).
				'()(?:/|'.self::preg_quote_atmark(self::SLASH_ESC).')(.+?)(&quot;)@',
			array($this, 'encodeLinksAndBackupTargets_clbk'),
			$xml
		);
		
		// モジュールが取りこぼしたファイルを回収
		if (preg_match_all('@<REFERENCE>(.+?)</REFERENCE>@', $xml, $m)) {
			$course = $this->getCourse();
			$source = $GLOBALS['CFG']->dataroot.'/'.$course->id.'/';
			$target = $this->getTempDir().'/course_files/';
			foreach ($m[1] as $file) {
				if (preg_match('@^\w+?\://@', $file)) {
					// 絶対パスは外部リンクなので何もしない
				} else {
					// 相対パス＝コースファイル
					SharingCart_FileSystem::copy(
						$source.$file,
						$target.$file
					);
				}
			}
		}
		
		if ($this->course->format == 'project') {
			// project フォーマットのセクションディレクトリ内のファイルはコースのルートに移動
			require_once $GLOBALS['CFG']->dirroot.'/course/format/project/lib.php';
			
			$section_directories = array();
			if ($css = $DB->get_records('course_sections', 'course', $this->course->id)) {
				foreach ($css as $cs) {
					if ($pt = $DB->get_record('course_project_title', 'sectionid', $cs->id)) {
						$section_directories[] = $pt->directoryname;
					}
				}
			}
			
			$xml = $this->moveUpSectionDirectories($xml, $section_directories);
		}
		
		return $xml;
	}
	protected function encodeLinksAndBackupTargets_clbk($m)
	{
		list (, $prefix, $course_id, $file_path, $suffix) = $m;
		
		if ($course_id == '') {
			// $@FILEPHP@$
			$course_id = $this->course->id;
		}
		
		// Moodle によってエスケープされたスラッシュやURLエンコードを復元
		$file_path = urldecode($file_path);
		$file_path = preg_replace('@'.self::preg_quote_atmark(self::SLASH_ESC).'@', '/', $file_path);
		
		$source_path = $GLOBALS['CFG']->dataroot.'/'.$course_id.'/'.$file_path;
		
		if ($course_id == SITEID) {
			// サイトファイル
			SharingCart_FileSystem::copy(
				$source_path,
				$this->getTempDir().'/site_files/'.$file_path,
				SharingCart_FileSystem::OVERWRITE
			);
			$link = parent::LINK_SITE_ESC.'/'.$file_path;
		} elseif ($course_id == $this->course->id) {
			// コースファイル (自身)
			SharingCart_FileSystem::copy(
				$source_path,
				$this->getTempDir().'/course_files/'.$file_path,
				SharingCart_FileSystem::OVERWRITE
			);
			$link = parent::LINK_FILE_ESC.'/'.$file_path;
		} else {
			// コースファイル (他)
			SharingCart_FileSystem::copy(
				$source_path,
				$this->getTempDir().'/'.parent::LINK_MISC_DIR.'/'.$course_id.'/'.$file_path,
				SharingCart_FileSystem::OVERWRITE
			);
			$link = parent::LINK_MISC_ESC.'/'.$course_id.'/'.$file_path;
		}
		
		return $prefix . $link . $suffix;
	}
	
	protected function moveUpSectionDirectories($xml, $section_directories)
	{
		$dirs = array_map(array(__CLASS__, 'preg_quote_atmark'), $section_directories);
		
		$xml = preg_replace_callback(
			'@(&quot;'.self::preg_quote_atmark(parent::LINK_FILE_ESC).'/)('.implode('|', $dirs).')/(.+?)(&quot;)@',
			array($this, 'moveUpSectionDirectories_clbk'),
			$xml
		);
		
		// リソースファイル用
		$xml = preg_replace_callback(
			'@(<REFERENCE>)('.implode('|', $dirs).')/(.+?)(</REFERENCE>)@',
			array($this, 'moveUpSectionDirectories_clbk'),
			$xml
		);
		
		return $xml;
	}
	protected function moveUpSectionDirectories_clbk($m)
	{
		list (, $prefix, $dir, $file_path, $suffix) = $m;
		
		$root_dir = $this->getTempDir().'/course_files';
		
		// ファイル移動
		SharingCart_FileSystem::move(
			$root_dir.'/'.$dir.'/'.$file_path,
			$root_dir.'/'.$file_path,
			SharingCart_FileSystem::OVERWRITE
		);
		
		// ディレクトリが空になったら削除する
		SharingCart_FileSystem::rmdir($root_dir.'/'.$dir);
		
		return $prefix . $file_path . $suffix;
	}
	
	protected static function preg_quote_atmark($s)
	{
		return preg_quote($s, '@');
	}
}

?>