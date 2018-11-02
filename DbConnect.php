<?php
class DbConnect
{

    private $host;
    private $dbname;
    private $username;
    private $password;
    private $port = 3306;
    private $is_ssh = false;
    private $ssh = [];

    /** @var  PDO */
    public $db;

    /**
     * DbConnect constructor.
     * @param $host
     */
    public function __construct($config = [])
    {
        $keyList = [
            'host', 'dbname', 'username', 'password'
        ];
        foreach ($keyList as $key) {
            if (!isset($config[$key])) {
                throw new Exception($key . ' 不存在');
            }
            $this->$key = $config[$key];
        }
        if (isset($config['port'])) {
            $this->port = $config['port'];
        }
        if (isset($config['is_ssh'])) {
            $this->is_ssh = $config['is_ssh'];

            $this->ssh = $config['ssh'];
        }
        if ($this->is_ssh) {
            //判断参数是否完整
            $sshKeyList = [
                'mysql_port', 'mysql_host', 'username', 'host'
            ];
            foreach ($sshKeyList as $sshKey) {
                if (!isset($this->ssh[$sshKey])) {
                    throw new Exception($sshKey . ' 不存在');
                }
            }
            $cmd = "ssh -fNg -L {$this->port}:{$this->ssh['mysql_host']}:{$this->ssh['mysql_port']} {$this->ssh['username']}@{$this->ssh['host']}";
            exec('netstat -an | grep ' . $this->port . ' |wc -l', $ret);

            if (trim($ret[0]) == 0) {
                //本地开启监听，需要输入ssh服务器密码

                $p = popen($cmd, 'r');
                pclose($p);
            }
        }

        $this->getDb();
    }

    private function getDb()
    {
        if (!$this->db) {

            $this->db = new \PDO("mysql:host={$this->host};dbname={$this->dbname};port={$this->port}", $this->username, $this->password);
            $this->db->query("SET NAMES utf8");
        }
        return $this->db;
    }

    public function query($sql)
    {
        $stat = $this->db->query($sql);
        if ($stat) {
            $list = $stat->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            print_r($this->db->errorInfo());
            die;
        }
        return $list;
    }

    public function exec($sql)
    {
        return $this->db->exec($sql);
    }

    public function array2sql($tableName, $data, $type = 'insert', $condition = '')
    {
        if (empty($data)) {
            return '';
        }
        if ($type == 'insert') {
            foreach ($data as $k => $v) {
                $data[$k] = str_replace('"', '\\"', str_replace('\\', '\\\\', $v));
            }
            $sql = 'insert into ' . $tableName . ' (`' . implode('`,`', array_keys($data)) . '`) values ("' . implode('","', array_values($data)) . '");';
        } else {
            $fields = [];
            foreach ($data as $key => $value) {
                $fields[] = '`' . $key . '`="' . str_replace('"', '\\"', str_replace('\\', '\\\\', $value)) . '"';
            }
            if ($condition) {
                $condition = ' where ' . $condition;
            }
            $sql = 'update ' . $tableName . ' set ' . implode(',', $fields) . $condition.';';
        }
        return $sql;
    }

}