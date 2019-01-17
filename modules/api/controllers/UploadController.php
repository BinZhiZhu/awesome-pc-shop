<?php

namespace common\modules\api\controllers;
use common\components\Request;
use common\components\Response;
use Exception;
use Yii;
use yii\web\Controller;

class UploadController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionIndex(){
        global $_GPC;
        global $_W;
        if (Yii::$app->request->get('i')) {
            $_GPC['file'] = 'file';
            Request::getInstance()->uniacid = $_W['uniacid'] = (int) Yii::$app->request->get('i');
            $_W['uniaccount'] = $_W['account'] = uni_fetch($_W['uniacid']);
            $_W['acid'] = (int) $_W['uniaccount']['acid'];
            $_W['attachurl'] = attachment_set_attach_url();
            try {
                $res = $this->Upload();
            } catch (Exception $e) {
                $error['error'] = $e->getCode();
                $error['message'] = $e->getMessage();
                \common\components\Response::jsonOutput($error);
            }
            \common\components\Response::jsonOutput($res);
        }else{
            $error['error'] = -2;
            $error['message'] = '参数错误!!';
            \common\components\Response::jsonOutput($error);
        }
    }
    public function Upload ()
    {
        global $_GPC;
        global $_W;

        load()->func('file');
        $field = $_GPC['file'];
        if (!empty($_FILES[$field]['name'])) {
            if (is_array($_FILES[$field]['name'])) {
                $files = array();

                foreach ($_FILES[$field]['name'] as $key => $name) {
                    $file = array('name' => $name, 'type' => $_FILES[$field]['type'][$key], 'tmp_name' => $_FILES[$field]['tmp_name'][$key], 'error' => $_FILES[$field]['error'][$key], 'size' => $_FILES[$field]['size'][$key]);
                    $ret = $this->uploadFile($file);

                    if ($ret['status'] == 'error') {
                        $ret = array('status' => 0);
                    } else {
                        $ret = array('status' => 1, 'filename' => $ret['path'], 'url' => trim($_W['attachurl'] . $ret['filename']));
                    }

                    $files[] = $ret;
                }

                return ['files' => $files];
            } else {
                $result = $this->uploadFile($_FILES[$field]);

                if ($result['status'] == 'error') {
                    throw new \common\modules\api\procedures\ApiException(\common\components\Response::UPLOAD_FAIL, $result['message']);
                }

                $files = [
                    ['status' => 1, 'url' => trim($_W['attachurl'] . $result['filename']), 'filename' => $result['filename']]
                ];
                return ['files' => $files];
            }
        } else {
            throw new \common\modules\api\procedures\ApiException(\common\components\Response::UPLOAD_NO_FILE, '未选择图片');
        }
    }

    protected function uploadFile($uploadfile)
    {
        global $_W;
        global $_GPC;
        $result['status'] = 'error';

        if ($uploadfile['error'] != 0) {
            $result['message'] = '上传失败';
            return $result;
        }

        load()->func('file');
        $path = '/images/ewei_shop/' . \common\components\Request::getInstance()->uniacid;

        if (!is_dir(ATTACHMENT_ROOT . $path)) {
            mkdirs(ATTACHMENT_ROOT . $path);
        }

        $_W['uploadsetting'] = array();
        $_W['uploadsetting']['image']['folder'] = $path;
        $_W['uploadsetting']['image']['extentions'] = $_W['config']['upload']['image']['extentions'];
        $_W['uploadsetting']['image']['limit'] = $_W['config']['upload']['image']['limit'];
        $file = file_upload($uploadfile, 'image');

        if (is_error($file)) {
            $ext = pathinfo($uploadfile['name'], PATHINFO_EXTENSION);
            $ext = strtolower($ext);
            $result['message'] = $file['message'] . ' 扩展名: ' . $ext . ' 文件名: ' . $uploadfile['name'];
            return $result;
        }

        if (function_exists('file_remote_upload')) {
            $remote = file_remote_upload($file['path']);

            if (is_error($remote)) {
                $result['message'] = $remote['message'];
                return $result;
            }
        }

        $result['status'] = 'success';
        $result['url'] = $file['url'];
        $result['error'] = 0;
        $result['filename'] = $file['path'];
        $result['url'] = trim($_W['attachurl'] . $result['filename']);
        pdo_insert('core_attachment', array('uniacid' => \common\components\Request::getInstance()->uniacid, 'uid' => $_W['member']['uid'], 'filename' => $uploadfile['name'], 'attachment' => $result['filename'], 'type' => 1, 'createtime' => TIMESTAMP));
        return $result;
    }
}
