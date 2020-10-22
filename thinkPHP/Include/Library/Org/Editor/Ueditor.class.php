<?php

namespace Org\Editor;

class Ueditor
{

    public function __construct($uid = '')
    {

        //导入设置
        $CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents("./Data/config/ueditor.json")), true);
        $action = I('get.action', '');

        //初始化上传设置数据
        $maxsize  = get_upload_maxsize(); //允许最大上传
        $file_ext = explode(',', C('CFG_UPLOAD_FILE_EXT'));
        $img_ext  = explode(',', C('CFG_UPLOAD_IMG_EXT'));
        foreach ($img_ext as $key => $val) {
            $img_ext[$key] = '.' . $val;
        }

        foreach ($file_ext as $key => $val) {
            $file_ext[$key] = '.' . $val;
        }
        $CONFIG['imageAllowFiles']        = array_values(array_intersect($CONFIG['imageAllowFiles'], $img_ext));
        $CONFIG['catcherAllowFiles']      = array_values(array_intersect($CONFIG['catcherAllowFiles'], $img_ext)); //远程图片
        $CONFIG['videoAllowFiles']        = array_values(array_intersect($CONFIG['videoAllowFiles'], $file_ext));
        $CONFIG['fileAllowFiles']         = array_values(array_intersect($CONFIG['fileAllowFiles'], $file_ext));
        $CONFIG['imageManagerAllowFiles'] = array_values(array_intersect($CONFIG['imageManagerAllowFiles'], $img_ext));
        $CONFIG['fileManagerAllowFiles']  = array_values(array_intersect($CONFIG['fileManagerAllowFiles'], $file_ext));

        $CONFIG['imageMaxSize']   = $maxsize;
        $CONFIG['scrawlMaxSize']  = $maxsize;
        $CONFIG['videoMaxSize']   = $maxsize;
        $CONFIG['fileMaxSize']    = $maxsize;
        $CONFIG['catcherMaxSize'] = $maxsize;

        switch ($action) {
            case 'config':
                $result = json_encode($CONFIG);
                break;

            case 'uploadimage':
                $config = array(
                    "pathFormat" => $CONFIG['imagePathFormat'],
                    "maxSize"    => $CONFIG['imageMaxSize'],
                    "allowFiles" => $CONFIG['imageAllowFiles'],
                );
                $field_name = $CONFIG['imageFieldName'];
                $result     = $this->uploadFile($config, $field_name, 1);
                break;

            case 'uploadscrawl':
                $config = array(
                    "pathFormat" => $CONFIG['scrawlPathFormat'],
                    "maxSize"    => $CONFIG['scrawlMaxSize'],
                    "allowFiles" => $CONFIG['scrawlAllowFiles'],
                    "oriName"    => "scrawl.png",
                );
                $field_name = $CONFIG['scrawlFieldName'];
                $result     = $this->uploadBase64($config, $field_name, 1);
                break;

            case 'uploadvideo':
                $config = array(
                    "pathFormat" => $CONFIG['videoPathFormat'],
                    "maxSize"    => $CONFIG['videoMaxSize'],
                    "allowFiles" => $CONFIG['videoAllowFiles'],
                );
                $field_name = $CONFIG['videoFieldName'];
                $result     = $this->uploadFile($config, $field_name, 0);
                break;

            case 'uploadfile':
                // default:
                $config = array(
                    "pathFormat" => $CONFIG['filePathFormat'],
                    "maxSize"    => $CONFIG['fileMaxSize'],
                    "allowFiles" => $CONFIG['fileAllowFiles'],
                );
                $field_name = $CONFIG['fileFieldName'];
                $result     = $this->uploadFile($config, $field_name, 0);
                break;

            case 'catchimage':
                $config = array(
                    "pathFormat" => $CONFIG['catcherPathFormat'],
                    "maxSize"    => $CONFIG['catcherMaxSize'],
                    "allowFiles" => $CONFIG['catcherAllowFiles'],
                    "oriName"    => "remote.png",
                );
                $field_name = $CONFIG['catcherFieldName'];
                $result     = $this->saveRemote($config, $field_name, 1);
                break;

            case 'listfile':
                $config = array(
                    'allowFiles' => $CONFIG['fileManagerAllowFiles'],
                    'listSize'   => $CONFIG['fileManagerListSize'],
                    'path'       => $CONFIG['fileManagerListPath'],
                    'filetype'   => 0,
                );
                $result = $this->listFile($config);
                break;

            case 'listimage':
                $config = array(
                    'allowFiles' => $CONFIG['imageManagerAllowFiles'],
                    'listSize'   => $CONFIG['imageManagerListSize'],
                    'path'       => $CONFIG['imageManagerListPath'],
                    'filetype'   => 1,
                );
                $result = $this->listFile($config);
                break;

            default:
                $result = json_encode(array(
                    'state' => '请求地址出错',
                ));
                break;

        }

        if (isset($_GET["callback"])) {
            if (preg_match("/^[\w_]+$/", $_GET["callback"])) {
                echo htmlspecialchars($_GET["callback"]) . '(' . $result . ')';
            } else {
                echo json_encode(array(
                    'state' => 'callback参数不合法',
                ));
            }
        } else {
            echo $result;
        }
    }

    /**
     *
     * uploadFile上传图片
     */
    private function uploadFile($config, $field_name, $img_flag = 1, $sub_path = '')
    {
        $yun_upload    = new \Common\Lib\YunUpload($img_flag, $sub_path, $field_name);
        $upload_result = $yun_upload->upload();

        if ($upload_result['status']) {
            $result['state']    = 'SUCCESS';
            $result['url']      = URL($upload_result['data'][0]['url']);
            $result['title']    = $upload_result['data'][0]['savename'];
            $result['type']     = '.' . $upload_result['data'][0]['ext'];
            $result['name']     = $upload_result['data'][0]['name'];
            $result['original'] = $upload_result['data'][0]['name'];
            $result['size']     = $upload_result['data'][0]['size'];

        } else {
            $result['state'] = $upload_result['info'];

        }
        return json_encode($result);
    }

    /**
     *
     * base64图片
     */
    private function uploadBase64($config, $field_name, $img_flag = 1, $sub_path = '')
    {
        $yun_upload    = new \Common\Lib\YunUpload($img_flag, $sub_path, $field_name);
        $upload_result = $yun_upload->uploadBase64();

        if ($upload_result['status']) {
            $result['state']    = 'SUCCESS';
            $result['url']      = $upload_result['data'][0]['url'];
            $result['title']    = $upload_result['data'][0]['savename'];
            $result['type']     = '.' . $upload_result['data'][0]['ext'];
            $result['name']     = $upload_result['data'][0]['name'];
            $result['original'] = $upload_result['data'][0]['name'];
            $result['size']     = $upload_result['data'][0]['size'];

        } else {
            $result['state'] = $upload_result['info'];

        }
        return json_encode($result);
    }

    /**
     *
     * 获取远程图片
     */
    private function saveRemote($config, $field_name, $img_flag = 1, $sub_path = '')
    {

        $yun_upload    = new \Common\Lib\YunUpload($img_flag, $sub_path, $field_name);
        $upload_result = $yun_upload->saveRemote();

        if ($upload_result['status']) {
            $result['state'] = 'SUCCESS';
            foreach ($upload_result['data'] as &$val) {
                $val['state']    = 'SUCCESS';
                $val['url']      = $val['url'];
                $val['title']    = $val['savename'];
                $val['type']     = '.' . $val['ext'];
                $val['original'] = $val['name'];
                $val['source']   = htmlspecialchars($val['source']);
                unset($val['info']);
            }
            $result['list'] = $upload_result['data'];

        } else {
            $result['state'] = $upload_result['info'];

        }
        return json_encode($result);

    }

    /**
     * 列出文件夹下所有文件，如果是目录则向下
     */
    private function listFile($config)
    {
        $allowFiles = substr(str_replace(".", "|", join("", $config['allowFiles'])), 1);
        $size       = isset($_GET['size']) ? htmlspecialchars($_GET['size']) : $config['listSize'];
        $start      = isset($_GET['start']) ? htmlspecialchars($_GET['start']) : 0;
        $limit      = $start . ',' . $size;

        //需要遍历的目录列表，最好使用缩略图地址，否则当网速慢时可能会造成严重的延时
        //$paths = './uploads/img1';
        if ($config['filetype'] == 1) {
            $where = array('filetype' => 1, 'haslitpic' => 1);
        } else {

            $where = array('filetype' => array('NEQ', 1));
        }

        //显示有缩略图　文件
        $files = M('attachment')->field('filepath,uploadtime')->where($where)->order('uploadtime DESC')->limit($limit)->select(); //最新50条

        if (!$files) {
            $files = array();
        }

        //return $files;
        if (!count($files)) {
            return json_encode(array(
                "state" => "no match file",
                "list"  => array(),
                "start" => $start,
                "total" => count($files),
            ));
        }

        //读取缩略图配置信息
        $imgtbSize = explode(',', C('CFG_IMGTHUMB_SIZE')); //配置缩略图第一个参数
        $imgTSize  = explode('X', $imgtbSize[0]);

        $list    = array();
        $sto_url = get_url_path(C('CFG_UPLOAD_ROOTPATH'));
        foreach ($files as $file) {
            $list[] = array('url' => $sto_url . $file['filepath'], 'mtime' => $file['uploadtime']);

        }

        /* 返回数据 */
        $result = json_encode(array(
            "state" => "SUCCESS",
            "list"  => $list,
            "start" => $start,
            "total" => count($files),
        ));

        return $result;
    }

}
