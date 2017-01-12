<?php


use Xxtime\Database\MySQL;

ini_set("date.timezone", "UTC");
ini_set('memory_limit', -1);
ini_set('max_execution_time', '0');
include __DIR__ . '/../vendor/autoload.php';

class AuditTools
{

    private $config;
    private $from;
    private $to;
    private $pdo;
    private $option;


    public function __construct()
    {
        $this->config = include_once 'config.php';
        $this->from = $this->config['from'];
        $this->to = $this->config['to'];
        $this->setOptions();
    }


    public function run()
    {
        $this->logger('START: Program');

        // 执行命令
        if (isset($this->option['method'])) {
            $method = $this->option['method'];
            if (method_exists($this, $method)) {
                $this->$method();
                exit();
            }
            exit("\r\n" . 'ERROR: no method [' . $method . "]\r\n\r\n");
        }

        $this->logger('END: Program');
    }


    /**
     * 导出订单
     */
    private function outTrade()
    {
        $this->logger('START: outTrade');
        foreach ($this->config['trade'] as $server_id => $server) {
            $fileName = "{$this->config['subject']}_tx_" . $server_id . '.csv';
            $sql = "SELECT CONCAT( server_id,'-',role_id ) user_id, amount, gold_real coin, pay_time time FROM order_log WHERE status='complete' AND pay_time>='{$this->from}' AND pay_time<='{$this->to}'";

            // SHELL
            $shell = "mysql -h{$server['host']} -P{$server['port']} -u{$server['user']} -p{$server['pass']} -e \"USE {$server['db']}; {$sql}\" >> /tmp/{$fileName}";
            $this->executeShell($shell);
        }
    }


    /**
     * 导出消耗
     */
    private function outExp()
    {
        $this->logger('START: outExp');
        foreach ($this->config['servers'] as $category => $list) {
            foreach ($list as $server_id => $server) {
                $fileName = "{$this->config['subject']}_exp_" . $category . '.csv';
                $sql = "SELECT CONCAT($server_id,'-',role_id) user_id, gold coin, reason type, log_time time FROM gold_log WHERE log_time>='{$this->from}' AND log_time<='{$this->to}'";

                // SHELL
                $shell = "mysql -h{$server['host']} -P{$server['port']} -u{$server['user']} -p{$server['pass']} -e \"USE {$server['db']}; {$sql}\" >> /tmp/{$fileName}";
                $this->executeShell($shell);
            }
        }
    }


    /**
     * 导出期末状态
     */
    private function outStatus()
    {
        $this->logger('START: outStatus');
        foreach ($this->config['servers'] as $category => $list) {
            foreach ($list as $server_id => $server) {
                $fileName = "{$this->config['subject']}_status_" . $category . '.csv';
                $sql = "SELECT CONCAT($server_id,'-',role_id) user_id, gold coin FROM role";

                // SHELL
                $shell = "mysql -h{$server['host']} -P{$server['port']} -u{$server['user']} -p{$server['pass']} -e \"USE {$server['db']}; {$sql}\" >> /tmp/{$fileName}";
                $this->executeShell($shell);
            }
        }

    }


    private function fixTrade()
    {
        $this->logger('START: fixTrade');
    }


    private function inTrade()
    {
        $this->logger('START: inTrade');
    }


    private function balanceExp()
    {
        $this->logger('START: balanceExp');
    }


    private function moveExp()
    {
        $this->logger('START: moveExp');
    }


    /**
     * @param string $shell
     */
    private function executeShell($shell = '')
    {
        exec($shell);
    }


    /**
     * @param $key
     * @return mixed
     */
    private function getPdo($key)
    {
        if (isset($this->pdo[$key])) {
            return $this->pdo[$key];
        }
        if (!isset($this->config['servers'][$key])) {
            $this->logger("no server: {$key}");
        }
        $config = [
            'host'     => $this->config['servers'][$key]['host'],
            'port'     => $this->config['servers'][$key]['port'],
            'database' => $this->config['servers'][$key]['db'],
            'username' => $this->config['servers'][$key]['user'],
            'password' => $this->config['servers'][$key]['pass'],
        ];
        $this->pdo[$key] = new MySQL($config);
        return $this->pdo[$key];
    }


    /**
     * 日志
     * @param string $msg
     */
    private function logger($msg = '')
    {
        print date('Y-m-d H:i:sO ') . $msg . "\r\n";
    }


    /**
     * 设置参数
     */
    private function setOptions()
    {
        $this->option = getopt('i::h::', ['method:']);
        if (isset($this->option['h'])) {
            $help = <<<END
-------------------------------------------
-h          帮助
-i          显示配置信息
--method    执行特定方法 例:audit_tools --method outTrade

操作步骤：
1. 导出CSV文件                  从原始数据源导出[outTrade,outExp,outStatus]
2. 导入CSV文件                  导入到审计数据库
3. 修正金额     [fixTrade]      设定目标修正金额 (仅操作订单)
4. 导入订单     [inTrade]       删除消耗中的订单记录,然后导入订单到消耗表
5. 手动检查                     检查测试数据,非法超大数据
6. 平衡消耗     [balanceExp]    无期末则补充,其他情况补消耗(期初+消耗=期末)
7. 移动消耗     [moveExp]       使其任意时间点(期初+消耗>0)
-------------------------------------------

END;
            print_r($help);
            exit;
        }
    }

}


$audit = new AuditTools();
$audit->run();