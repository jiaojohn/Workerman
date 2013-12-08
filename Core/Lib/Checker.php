<?php
namespace WORKERMAN\Core\Lib;
/**
 * 环境检查相关
 * 
* @author walkor <worker-man@qq.com>
 */
class Checker
{
    
    /**
     * 检查启动worker进程的的用户是否合法
     * @return void
     */
    public static function checkWorkerUserName($worker_user)
    {
        if($worker_user)
        {
            $user_info = posix_getpwnam($worker_user);
            return !empty($user_info);
        }
    }
    
    /**
     * 检查扩展支持情况
     * @return void
     */
    public static function checkExtension()
    {
        // 扩展名=>是否是必须
        $need_map = array(
                        'posix'     => true,
                        'pcntl'     => true,
                        'sysvshm'   => false,
                        'sysvmsg'   => false,
                        'libevent'  => false,
                        'ev'        => false,
                        'uv'        => false,
                        'proctitle' => false,
        );
    
        // 检查每个扩展支持情况
        echo "----------------------EXTENSION--------------------\n";
        $pad_length = 26;
        foreach($need_map as $ext_name=>$must_required)
        {
            $suport = extension_loaded($ext_name);
            if($must_required && !$suport)
            {
                \WORKERMAN\Core\Master::notice($ext_name. " [NOT SUPORT BUT REQUIRED] \tYou have to compile CLI version of PHP with --enable-{$ext_name} \tServer start fail");
                exit($ext_name. " \033[31;40m [NOT SUPORT BUT REQUIRED] \033[0m\n\n\033[31;40mYou have to compile CLI version of PHP with --enable-{$ext_name} \033[0m\n\n\033[31;40mServer start fail\033[0m\n\n");
            }
    
            // 支持扩展
            if($suport)
            {
                echo str_pad($ext_name, $pad_length), "\033[32;40m [OK] \033[0m\n";
            }
            // 不支持
            else
            {
                // ev uv inotify不是必须
                if('ev' == $ext_name || 'uv' == $ext_name || 'proctitle' == $ext_name)
                {
                    continue;
                }
                echo str_pad($ext_name, $pad_length), "\033[33;40m [NOT SUPORT] \033[0m\n";
            }
        }
    }
    
    /**
     * 检查禁用的函数
     * @return void
     */
    public static function checkDisableFunction()
    {
        // 可能禁用的函数
        $check_func_map = array(
                        'stream_socket_server',
                        'stream_socket_client',
                        'pcntl_signal_dispatch',
        );
        if($disable_func_string = ini_get("disable_functions"))
        {
            $disable_func_map = array_flip(explode(',', $disable_func_string));
        }
        // 遍历查看是否有禁用的函数
        foreach($check_func_map as $func)
        {
            if(isset($disable_func_map[$func]))
            {
                \WORKERMAN\Core\Master::notice("Function $func may be disabled\tPlease check disable_functions in php.ini \t Server start fail");
                exit("\n\033[31;40mFunction $func may be disabled\nPlease check disable_functions in php.ini\033[0m\n\n\033[31;40mServer start fail\033[0m\n\n");
            }
        }
    }
    
    /**
     * 检查worker配置、worker语法错误等
     * @return void
     */
    public static function checkWorkersConfig()
    {
        $pad_length = 26;
        $total_worker_count = 0;
        // 检查worker 是否有语法错误
        echo "----------------------WORKERS--------------------\n";
        foreach (Config::get('workers') as $worker_name=>$config)
        {
            if(isset($config['socket']))
            {
                // 端口、协议、进程数等信息
                if(empty($config['socket']['port']))
                {
                    \WORKERMAN\Core\Master::notice(str_pad($worker_name, $pad_length)." [port not set] \t Server start fail");
                    exit(str_pad($worker_name, $pad_length)."\033[31;40m [port not set] \033[0m\n\n\033[31;40mServer start fail\033[0m\n");
                }
                if(empty($config['socket']['protocol']))
                {
                    \WORKERMAN\Core\Master::notice(str_pad($worker_name, $pad_length)." [protocol not set]\tServer start fail");
                    exit(str_pad($worker_name, $pad_length)."\033[31;40m [protocol not set]\033[0m\n\n\033[31;40mServer start fail\033[0m\n");
                }
            }
            if(empty($config['children_count']))
            {
                \WORKERMAN\Core\Master::notice(str_pad($worker_name, $pad_length)." [children_count not set]\tServer start fail");
                exit(str_pad($worker_name, $pad_length)."\033[31;40m [children_count not set]\033[0m\n\n\033[31;40mServer start fail\033[0m\n");
            }
    
            $total_worker_count += $config['children_count'];
    
            // 语法检查
            if(0 != self::checkSyntaxError(WORKERMAN_ROOT_DIR . "Workers/$worker_name.php", $worker_name))
            {
                unset(Config::instance()->config['workers'][$worker_name]);
                \WORKERMAN\Core\Master::notice("$worker_name has Fatal Err");
                echo str_pad($worker_name, $pad_length),"\033[31;40m [Fatal Err] \033[0m\n";
                continue;
            }
            
            if(isset($config['user']))
            {
                $worker_user = $config['user'];
                if(!self::checkWorkerUserName($worker_user))
                {
                    echo str_pad($worker_name, $pad_length),"\033[31;40m [FAIL] \033[0m\n";
                    \WORKERMAN\Core\Master::notice("Can not run $worker_name processes as user $worker_user , User $worker_user not exists\tServer start fail");
                    exit("\n\033[31;40mCan not run $worker_name processes as user $worker_user , User $worker_user not exists\033[0m\n\n\033[31;40mServer start fail\033[0m\n\n");
                }
            }
            
            echo str_pad($worker_name, $pad_length),"\033[32;40m [OK] \033[0m\n";
        }
    
        if($total_worker_count > \WORKERMAN\Core\Master::SERVER_MAX_WORKER_COUNT)
        {
            \WORKERMAN\Core\Master::notice("Number of worker processes can not be more than " . \WORKERMAN\Core\Master::SERVER_MAX_WORKER_COUNT . ".\tPlease check children_count in " . WORKERMAN_ROOT_DIR . "config/main.php\tServer start fail");
            exit("\n\033[31;40mNumber of worker processes can not be more than " . \WORKERMAN\Core\Master::SERVER_MAX_WORKER_COUNT . ".\nPlease check children_count in " . WORKERMAN_ROOT_DIR . "config/main.php\033[0m\n\n\033[31;40mServer start fail\033[0m\n");
        }
    
        echo "-------------------------------------------------\n";
    }
    
    /**
     * 检查worker文件是否有语法错误
     * @param string $worker_name
     * @return int 0：无语法错误 其它:可能有语法错误
     */
    public static function checkSyntaxError($file, $class_name = null)
    {
        $pid = pcntl_fork();
        // 父进程
        if($pid > 0)
        {
            // 退出状态不为0说明可能有语法错误
            $pid = pcntl_wait($status);
            return $status;
        }
        // 子进程
        elseif($pid == 0)
        {
            // 载入对应worker
            require_once $file;
            if($class_name && !class_exists($class_name))
            {
                throw new \Exception("Class $class_name not exists");
            }
            exit(0);
        }
    }
    
    /**
     * 检查打开文件限制
     * @return void
     */
    public static function checkLimit()
    {
        if($limit_info = posix_getrlimit())
        {
            if('unlimited' != $limit_info['soft openfiles'] && $limit_info['soft openfiles'] < \WORKERMAN\Core\Master::MIN_SOFT_OPEN_FILES)
            {
                echo "Notice : Soft open files now is {$limit_info['soft openfiles']},  We recommend greater than " . \WORKERMAN\Core\Master::MIN_SOFT_OPEN_FILES . "\n";
            }
            if('unlimited' != $limit_info['hard filesize'] && $limit_info['hard filesize'] < \WORKERMAN\Core\Master::MIN_SOFT_OPEN_FILES)
            {
                echo "Notice : Hard open files now is {$limit_info['hard filesize']},  We recommend greater than " . \WORKERMAN\Core\Master::MIN_HARD_OPEN_FILES . "\n";
            }
        }
    }
    
    /**
     * 检查配置的pid文件是否可写
     * @return void
     */
    public static function checkPidFile()
    {
        // 已经有进程pid可能server已经启动
        if(@file_get_contents(WORKERMAN_PID_FILE))
        {
            \WORKERMAN\Core\Master::notice("Server already started", true);
            exit;
        }
        
        if(is_dir(WORKERMAN_PID_FILE))
        {
            exit("\n\033[31;40mpid-file ".WORKERMAN_PID_FILE." is Directory\033[0m\n\n\033[31;40mServer start failed\033[0m\n\n");
        }
        
        $pid_dir = dirname(WORKERMAN_PID_FILE);
        if(!is_dir($pid_dir))
        {
            if(!mkdir($pid_dir, true))
            {
                exit("Create dir $pid_dir fail\n");
            }
        }
        
        if(!is_writeable($pid_dir))
        {
            exit("\n\033[31;40mYou should start the server as root\033[0m\n\n\033[31;40mServer start failed\033[0m\n\n");
        }
        
    }
}