<?php

require 'yield/schedule.php';
require 'yield/task.php';
require 'yield/medoo.php';


// 报告 runtime 错误
error_reporting(E_ERROR | E_WARNING | E_PARSE);
//设置最大执行时间
set_time_limit(0);

class Test
{

    public static $db = false;      //DB类
    public $ql = false;     //模拟请求类
    public $_url = false;   //url和域名
    public $IPS = false;   //IP地址和端口
    public static $status;  //远程端口状态
    public static $error = array();     //错误信息
    public static $success = array();

    /**
     *  js模拟请求
     */
    public function __construct()
    {
        // Initialize
        self::$db = new \Medoo\Medoo([
            'database_type' => 'mysql',
            'database_name' => 'openapi',
            'server' => 'localhost',
            'username' => 'root',
            'password' => 'root'
        ]);


    }

    public function check_fpm_by_api($url, $port = 8080)
    {

        $info = [
            'api' => 'utils.util.sessionid',
            'version' => '1.0'
        ];

        $url = 'http://' . $url . ':' . $port;

        try {
            $context = stream_context_create(
                array(
                    'http' => array(
                        'method' => 'POST',
                        'header' => 'OA-App-Key: 1001520',
                        'content' => http_build_query($info),
                        'timeout' => 15
                    )
                )
            );

            $result = file_get_contents($url, false, $context);
            $info = json_decode($result);

            //检查是否验证通过
            if (!$info->success) {
                self::$error[] = $result;
            } else {
                self::$success[] = $result;
            }
        } catch (Exception $e) {
            self::$error[] = $e->getMessage();
        }

    }

    /**
     * Notes:curl请求方法
     * create_User: tenger
     * @param $url 请求的url地址
     * @param array $params 请求参数
     * @param bool $is_post 是否为post请求
     * @param int $time_out 请求时长
     * @param array $header header头设置
     *
     * @return mixed
     */
    public function curlRequest($url, $params = array(), $is_post = false, $time_out = 10, $header = array())
    {
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置是否返回response header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上


        //当需要通过curl_getinfo来获取发出请求的header信息时，该选项需要设置为true
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        curl_setopt($ch, CURLOPT_TIMEOUT, $time_out);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $time_out);

        //Post请求
        if ($is_post) {
            curl_setopt($ch, CURLOPT_POST, $is_post);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        //自定义header头
        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        //请求结果
        $response = curl_exec($ch);

//        $request_header = curl_getinfo( $ch, CURLINFO_HEADER_OUT);  //查看请求header信息
//        $request_header = curl_getinfo( $ch,CURLINFO_HEADER_SIZE);  //查看post字段信息

        curl_close($ch);

        return $response;
    }

    /**
     * 检查url
     * @param $ips
     */
    public function check_url($url)
    {

        $url = trim($url);
        return $this->curlRequest($url);

    }


    /**
     * Notes:通过网关验证部署好的FPM主机
     * create_User: tenger
     * @param $url
     * @param int $port
     * @param bool $is_https
     */
    public function check_remote_url($url, $port = 8080, $is_https = false)
    {

        try {
            //是否为https请求
            $http = $is_https ? ('https://' . $url . ':' . $port) : ('http://' . $url . ':' . $port);

            //请求参数
            $params = [
                'api' => 'utils.util.sessionid',
                'version' => '1.0'
            ];

            //header头
            $header = [
                "Content-Type" => 'application/x-www-form-urlencoded',
                "OA-App-Key" => 1001520,
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3278.0 Safari/537.36'
            ];

            //模拟post的请求
            $result = $this->curlRequest($http, $params, true, 30, $header);
            self::$success[] = json_decode($result);

        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }

    }


    /**
     * Notes:创建临时表
     * create_User: tenger
     *
     * @return bool
     */
    public function create_temp_table($ip='localhost', $port=8080)
    {

        try {

            //查询数据表是否存在着
            $info = self::$db->query(
                "SELECT COUNT(1) AS flag FROM information_schema.tables WHERE table_schema='openapi' AND table_name = 'temp_shared_spo';"
            )->fetch();

            //表不存在则新建临时表
            if (!$info['flag']) {
                self::$db->query("CREATE TABLE `temp_shared_spo` LIKE `shared_spo`;");
            }

            //复制数据到临时表中
            self::$db->query("INSERT INTO `temp_shared_spo` SELECT * FROM `shared_spo`;");

            //验证数据是否已经生成好
            $data = self::$db->get("temp_shared_spo", "communication_addr", [
                "id" => 1
            ]);

            if (empty($data)) {
                throw new Exception('获取临时表数据失败~');
            } else {
                self::$success[] = array('suceess' => '建表成功', 'code' => 200);
            }
        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }
    }

    /**
     * 获取ip
     * @return array
     */
    public function get_ips()
    {

        try {
            $data = self::$db->get('shared_spo', [
                'communication_addr[JSON]'
            ], [
                'id' => 1
            ]);

            $ips = $data['communication_addr'];

            return $ips;
        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }


    }


    /**
     * 新增FPM主机IP操作
     * @param $ip
     * @param int $port
     * @return bool|mixed|null|string|string[]
     */
    public function update_ip($ip, $port = 8080)
    {

        try {

            //获取原数据库的ip字段
            $old_ips = $this->get_ips();

            //把新的ip字段添加进去
            array_push($old_ips, $ip . ':' . $port);

            //json格式更新操作
            $info = self::$db->update('temp_shared_spo', [
                "communication_addr[JSON]" => $old_ips
            ], [
                "id" => 1
            ]);

            $last_sql = str_replace("temp_shared_spo", "shared_spo", self::$db->last());

            if ($info->rowCount()) {
                self::$success[] = array('success' => $ip . ' 如没有任何错误信息,请执行以下sql：', 'code' => 200, 'update' => $last_sql);
            } else {
                self::$error[] = array('error' => $ip . ' 临时表更新失败，影响行数0', 'error_info' => self::$db->last());
            }
        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }
    }

    /**
     * 模拟header头访问远程FPM状态
     * @param $ip ip地址或域名
     * @param bool $is_https 是否使用https访问，默认为http
     */
    public function check_fpm_status($ip, $is_https = false)
    {
        try {
            //组装ip或域名
            $ip = $is_https ? 'https://' . $ip : 'http://' . $ip;

            $this->curlRequest($ip);

        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }
    }


    /**
     * 绑定本地host文件
     * @param $ip
     * @param $domain
     * @return bool|string
     */
    public function add_host($ip, $domain)
    {

        try {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                self::$error[] = array('error' => '请输入正确的IP地址~', 'code' => 2001);
                return false;
            } else if (empty($domain)) {
                self::$error[] = array('error' => '请填写域名~', 'code' => 2002);
                return false;
            } else {
                $newdomain = $ip . ' ' . $domain . PHP_EOL;
                if (strcasecmp(PHP_OS, 'WINNT') === 0) {
                    // Windows 服务器下
                    if (file_put_contents('C:\Windows\System32\drivers\etc\hosts', $newdomain, FILE_APPEND)) {
                        self::$success[] = array('success' => '绑定域名成功~', 'code' => 200);
                        return true;
                    } else {
                        self::$error[] = array('error' => '绑定域名失败~', 'code' => 2005);
                        return false;
                    }
                } elseif (strcasecmp(PHP_OS, 'Linux') === 0) {
                    // Linux 服务器下
                    if (file_put_contents('/etc/hosts', $newdomain, FILE_APPEND)) {
                        self::$success[] = array('success' => '添加成功~', 'code' => 200);
                        return true;
                    } else {
                        self::$error[] = array('error' => '绑定域名失败~', 'code' => 2005);
                        return false;
                    }
                }

            }
        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }
    }


    /**
     * 检测能否ping通IP或域名
     * @param type $address IP地址或域名地址
     * @return boolean
     */
    public function ping_address($address)
    {
        try {
            $status = -1;
            if (strcasecmp(PHP_OS, 'WINNT') === 0) {
                // Windows 服务器下
                $pingresult = exec("ping -n 1 {$address}", $outcome, $status);
            } elseif (strcasecmp(PHP_OS, 'Linux') === 0) {
                // Linux 服务器下
                $pingresult = exec("ping -c 1 {$address}", $outcome, $status);
            }

            if (0 == $status) {
                self::$success[] = array('success' => "请求ip: " . $address . " 成功~", 'code' => 200);
                return true;
            } else {
                self::$error[] = array('error' => "请求ip: " . $address . " 失败~", 'code' => 2003);
                return false;
            }
        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }

    }


    /**
     * 验证远程ip的端口是否开启
     * @param $ip
     * @param $port
     * @return int
     */
    public function check_remote_port($ip, $port = 8080)
    {
        try {

            $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

            if ($sock === false) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                throw new Exception($errormsg, $errorcode);
            }
//        socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);   //每次请求后注意关闭连接
            socket_set_nonblock($sock);

            try{
                socket_connect($sock, $ip, $port);
            }catch(Exception $e){
                throw new Exception(socket_connect($sock, $ip, $port));
            }
            socket_set_block($sock);
            self::$status = socket_select($r = array($sock), $w = array($sock), $f = array($sock), 5);
            $info = self::check_status($ip, $port);

        }catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }


    }


    /**u
     * 返回远程端口号验证结果
     */
    public static function check_status($ip, $port)
    {
        $host = $ip . ': ' . $port;
        switch (self::$status) {
            case 2:
                self::$error[] = array('error' => $host . ' 端口未开启');
                return false;
            case 1:
                self::$success[] = array('success' => $host . ' 端口正常');
                return true;
            case 0:
                self::$error[] = array('error' => $host . ' 连接超时');
                return false;
        }
    }


    /**
     * Notes:对内外网的ip进行处理
     * create_User: tenger
     * @param $outips 外网ip
     * @param $inips 内网ip
     *
     * @return array
     */
    public function deal_ip_array($outips, $inips)
    {
        try {

            if (count($outips) == count($inips)) {
                $new_ips = array_combine($outips, $inips);

                //获取原有表中的ip，验证并除去重复的ip
                $datas = $this->get_ips();

                foreach ($new_ips as $out => $inner) {

                    if($out == $inner){
                        self::$error[] = array('error' =>'外网ip： ' .$out . '与内网ip'.$inner . '相同');
                        return false;
                    }

                    //验证外网ip
                    list($out_ip, $port) = explode(':', $out);
                    if (!filter_var($out_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        self::$error[] = array('error' => $out_ip . ' 格式不正确,请输入正确的外网IP地址~', 'code' => 2001);
                    }

                    //验证内外ip
                    list($in_ip, $port) = explode(':', $inner);
                    if (!filter_var($in_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        self::$error[] = array('error' => $in_ip . ' 格式不正确,请输入正确的内网IP地址~', 'code' => 2001);
                    }

                    //去重
                    if (in_array($inner, $datas)) {
                        unset($new_ips[$out]);
                        self::$error[] = array('error' => $inner . ' 已经存在,请检查输入IP是否正确~', 'code' => 2007);
                    }
                    continue;
                }

                return $new_ips;

            } else {
                self::$error[] = '请检查内外网ip是否一一对应';
            }


        } catch (Exception $e) {//捕获异常
            self::$error[] = $e->getMessage();
        }
    }


    /**
     * Notes:协程迭代依次处理
     * create_User: tenger
     * @param $datas    客户端提交的ip数组
     * @param $method   需要用到的方法名
     *
     * @return Generator
     */
    public function step($datas, $method)
    {
        $method = trim($method);

        foreach ($datas as $out_ip => $inner_ip) {

            if ($method == 'update_ip') {
                list($ip, $port) = explode(':', $inner_ip);
            }else{
                list($ip, $port) = explode(':', $out_ip);
            }
            $this->$method($ip, $port);
            yield;
        }
    }


    /**
     * 提示信息
     * @param $data
     */
    public static function tips($data)
    {
        echo json_encode($data);
    }

}


if ($_POST) {
    $Check_Obj = new Test();
    $info = $_POST;
    $step = $info['step'];
    $outips = $_POST['outip'];
    $inips = $_POST['inip'];

    //用来存放返回值的
    $return_data = array();

    $IPS = $Check_Obj->deal_ip_array($outips, $inips);

    if(!$IPS){
        $return_data['error'] = $Check_Obj::$error ? $Check_Obj::$error : false;
        echo json_encode($return_data);
        exit;
    }


    //协程迭代依次处理
    $scheduler = new Scheduler;

    $scheduler->newTask($Check_Obj->step($IPS, 'check_remote_port'));
    $scheduler->newTask($Check_Obj->step($IPS, 'check_remote_url'));
    $scheduler->newTask($Check_Obj->step($IPS, 'create_temp_table'));
    $scheduler->newTask($Check_Obj->step($IPS, 'update_ip'));

    $scheduler->run();


    //返回处理成功和失败的信息
    $return_data['success'] = $Check_Obj::$success ? $Check_Obj::$success : false;
    $return_data['error'] = $Check_Obj::$error ? $Check_Obj::$error : false;
    echo json_encode($return_data);




}




