<?php
/**
    *  功能：下载B站右上角小图标，萌化你我他的心~  
    *  PS：妈耶，卧槽，真的萌 (/≧▽≦/) 
*/
class BiliIconDownload{
    /**
     * 默认上传配置
     * @var array
     */
    private $config = array(  
        'file_save_path'        => "/BiliBiliIcon/",  // 文件保存路径
        'max_process_num'       => 10,             // 最大开启进程数量
        'exts'                  => array('jpg', 'gif', 'png', 'jpeg'),       //允许上传的文件后缀
    );

    private $download_config = array();   // 下载文件设置

    public function __construct( $download_config = array(),$config = array() ){

        if( !empty($download_config) ){
            $this->download_config = $download_config;
        }else{
            exit('找不到数据啊~~');
        }

        /* 获取配置 */
        $this->config   =   array_merge($this->config, $config);
        
        /* 调整配置，把字符串配置参数转换为数组 */
        if(!empty($this->config['exts'])){
            if (is_string($this->config['exts'])){
                $this->config['exts'] = explode(',', $this->config['exts']);
            }
            $this->config['exts'] = array_map('strtolower', $this->config['exts']);
        }
    }

    public function download(){

        // 已处理的数量
        $handle_num = 0;
        
        // 未处理完成
        while(count($this->download_config)>0){

            // 需要处理的大于最大进程数
            if(count($this->download_config)>$this->config['max_process_num']){
                $process_num = $this->config['max_process_num'];
            // 需要处理的小于最大进程数
            }else{
                $process_num = count($this->download_config);
            }

            // 抽取指定数量进行下载  规定每次下载的数量为 最大开启进程数量
            $tmp_download_config = array_splice($this->download_config, 0, $process_num);

            // 执行下载
            $result = $this->process($tmp_download_config);

            // 记录已处理的数量
            $handle_num += count($result);

        }
        return $handle_num;
    }

    /**
     * 多进程下载文件
     * @param  Array $download_config 本次下载的设置
     * @return Array
     */
    private function process($download_config){

        /* 检查上传目录 */
        $file_save_path = dirname(__FILE__).$this->config['file_save_path'];
        if(!file_exists($file_save_path)){
            $dir = mkdir($file_save_path,0777,true);
            if(!$dir){
                exit('文件创建失败请手动创建文件夹：'.$file_save_path);
            }
        }

        // 文件资源
        $fp = array();

        // curl会话
        $ch = array();

        // 执行结果
        $result = array();

        // 创建curl handle
        $mh = curl_multi_init();

        // 循环设定数量
        foreach($download_config as $k=>$value){
            $ext = explode('.',$value['url']); 
            $ext = $ext[count($ext)-1];
            
            /* 检查文件后缀 */
            if( !$this->checkExt($ext) ){
                exit('上传文件后缀不允许');
            }
            $file_name = $file_save_path.iconv("UTF-8", "GB2312//IGNORE", $value['title']).'.'.$ext;
            $download_config[$k]['title'] = $file_save_path.$value['title'].'.'.$ext;

            $ch[$k] = curl_init();
            $fp[$k] = fopen( $file_name , 'a');
            curl_setopt($ch[$k], CURLOPT_URL, $value['url']);
            curl_setopt($ch[$k], CURLOPT_FILE, $fp[$k]);
            curl_setopt($ch[$k], CURLOPT_HEADER, 0);
            curl_setopt($ch[$k], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch[$k], CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)');

            // 加入处理
            curl_multi_add_handle($mh, $ch[$k]);
        }

        $active = null;

        do{
            $mrc = curl_multi_exec($mh, $active);
        } while($active);

        // 获取数据
        foreach($fp as $k=>$v){
            fwrite($v, curl_multi_getcontent($ch[$k]));
        }

        // 关闭curl handle与文件资源
        foreach($download_config as $k=>$value){
            curl_multi_remove_handle($mh, $ch[$k]);
            fclose($fp[$k]);

            // 检查是否下载成功
            if(file_exists($value['title'])){
                $result[$k] = true;
            }else{
                
                $result[$k] = false;
            }
        }

        curl_multi_close($mh);

        return $result;
    }

    /**
     * 检查上传的文件后缀是否合法
     * @param string $ext 后缀
     */
    private function checkExt($ext) {
        return empty($this->config['exts']) ? true : in_array(strtolower($ext), $this->config['exts']);
    }

}

//数据准备
$url = 'https://www.bilibili.com/index/index-icon.json';
$bili_icon = @file_get_contents($url);
$bili_icon = json_decode($bili_icon,true);

if( empty($bili_icon) ){
    exit('找不到数据啊~~');
}

$bili_icon_data = array();
foreach ($bili_icon["fix"] as $key => $value) {
    $bili_icon_data[$key]['url']    = 'http:'.$value['icon'];
    $bili_icon_data[$key]['title']  = !empty($value['title'])?$value['title']:time();
}
unset($bili_icon);

//开始下载数据
$obj = new BiliIconDownload( $bili_icon_data );
$handle_num = $obj->download();
echo '滴滴滴滴滴快递送达';