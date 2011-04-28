<?php

require_once '../../config.php';

//error_reporting(E_ALL);

require_once './shared/SharingCart_Restore.php';
require_once './sharing_cart_table.php';

$sc_id      = required_param('id', PARAM_INT);
$course_id  = required_param('course', PARAM_INT);
$section_id = required_param('section', PARAM_INT);
$return_to  = urldecode(required_param('return', PARAM_LOCALURL));

// 共有アイテムが存在するかチェック
$sharing_cart = sharing_cart_table::get_record_by_id($sc_id)
    or print_error('err_shared_id', 'block_sharing_cart', $return_to);

// 自分が所有する共有アイテムかチェック
$sharing_cart->userid == $USER->id
    or print_error('err_capability', 'block_sharing_cart', $return_to);

// ZIPファイル名取得
// $zip_name = $sharing_cart->file;

$fs = get_file_storage();
$file = $fs->get_file_by_id($sharing_cart->fileid);
$file->copy_content_to($CFG->dataroot."/temp/backup/backup.mbz");

try {

    // リストアオブジェクト (※ $restore は Moodle グローバル変数として予約されているので使用不可)
    $worker = new SharingCart_Restore($course_id, $section_id);

    // サイレントモード
    $worker->setSilent();

    // 設定開始
    $worker->beginPreferences();

    // ZIPファイル名設定
    //  $worker->setZipName($zip_name);

    // 設定完了
    $worker->endPreferences();

    // リストア実行
    $worker->execute();


    if ($worker->succeeded()) {
        // 成功：リダイレクト
        redirect($return_to);
    } else {
        // 失敗：「続行」画面
        print_continue($return_to);
    }

} catch (SharingCart_CourseException $e) {
    //print_error('err_course_id', 'block_sharing_cart', $return_to);
    error((string)$e); // デバッグ用に詳細メッセージを表示

} catch (SharingCart_SectionException $e) {
    //print_error('err_section_id', 'block_sharing_cart', $return_to);
    error((string)$e); // デバッグ用に詳細メッセージを表示

} catch (SharingCart_ModuleException $e) {
    //print_error('err_module_id', 'block_sharing_cart', $return_to);
    error((string)$e); // デバッグ用に詳細メッセージを表示

} catch (SharingCart_XmlException $e) {
    //print_error('err_invalid_xml', 'block_sharing_cart', $return_to);
    error((string)$e); // デバッグ用に詳細メッセージを表示

} catch (SharingCart_Exception $e) {
    //print_error('err_backup', 'block_sharing_cart', $return_to);
    error((string)$e); // デバッグ用に詳細メッセージを表示

}

?>