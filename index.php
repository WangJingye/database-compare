<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2017/6/1
 * Time: 下午5:11
 */

require_once dirname(__FILE__) . '/DbConnect.php';
ini_set('display_errors', 'On');
$local_config = [
    'host' => '127.0.0.1',
    'dbname' => 'bw_erp',
    'username' => 'root',
    'password' => '',
];
$remote_config = [
    'host' => '127.0.0.1',
    'dbname' => 'bw_erp',
    'username' => 'hercules',
    'password' => 'zWeAJ7ep2ECPxK',
    'port' => '3307',
    'is_ssh' => true,
    'ssh' => [ //远程数据库链接
        'host' => '',
        'username' => '',
        'mysql_host' => '',
        'mysql_port' => '3306'
    ]
];
//$remote_config= [
//    'host' => '127.0.0.1',
//    'dbname' => 'bw_erp',
//    'username' => 'root',
//    'password' => 'root521',
//    'port'=>'3307',
//    'is_ssh'=>true,
//    'ssh'=>[
//        'host'=>'47.96.14.194',
//        'username'=>'thomas',
//        'mysql_host'=>'127.0.0.1',
//        'mysql_port'=>'3306'
//    ]
//];
//$local_config= [
//    'host' => '172.100.10.20',
//    'dbname' => 'bw_erp',
//    'username' => 'hercules',
//    'password' => 'Hercules.123',
//];
$sql = 'show full tables';
$localDb = new DbConnect($local_config);
$localData = $localDb->query($sql);

$remoteDb = new DbConnect($remote_config);
$remoteData = $remoteDb->query($sql);

$remoteTables = [];
foreach ($remoteData as $v) {
    $remoteTables[] = $v['Tables_in_' . $remote_config['dbname']];
}
$localTables = [];
foreach ($localData as $v) {
    $localTables[] = $v['Tables_in_' . $local_config['dbname']];
}
$differenceTables = [];
$createTables = [];
foreach ($localTables as $localTable) {
    $sql = 'show full columns from ' . $localTable;
    $arr = [];
    if (in_array($localTable, $remoteTables)) {

        $localTableData = $localDb->query($sql);
        $remoteTableData = $remoteDb->query($sql);

        $remoteFields = [];
        foreach ($remoteTableData as $v) {
            $remoteFields[$v['Field']] = $v['Type'];

        }
        $k = '';
        foreach ($localTableData as $v) {
            if ((isset($remoteFields[$v['Field']]) && $remoteFields[$v['Field']] != $v['Type']) || !isset($remoteFields[$v['Field']])) {
                $differenceTables[] = [
                    'table' => $localTable,
                    'field' => $v['Field'],
                    'fromType' => isset($remoteFields[$v['Field']]) ? $remoteFields[$v['Field']] : '',
                    'toType' => $v['Type'],
                    'default' => $v['Default'],
                    'comment' => $v['Comment'],
                    'after' => $k,
                ];
            }
            $k = $v['Field'];
        }
    } else {
        $sql = 'show create table ' . $localTable;
        $result1 = $localDb->query($sql);
        $createTables[] = $result1[0]['Create Table'];
    }

}
$databaseDiffFile = dirname(__FILE__) . '/database—diff.sql';
file_put_contents($databaseDiffFile, "");

foreach ($differenceTables as $differenceTable) {

    if ($differenceTable['fromType'] == '') {
        $type = 'add';
    } else {
        $type = 'modify';
    }
    $sql = "alter table `{$differenceTable['table']}` {$type} `{$differenceTable['field']}` {$differenceTable['toType']}";
    if ($differenceTable['default'] !== NULL) {
        $sql .= " DEFAULT \"" . $differenceTable['default'] . "\"";
    }
    if ($differenceTable['comment']) {
        $sql .= " comment \"" . $differenceTable['comment'] . "\"";
    }
    if ($differenceTable['after']) {
        $sql .= " after `{$differenceTable['after']}`";
    }
    $sql .= ';';
    file_put_contents($databaseDiffFile, $sql . "\n", FILE_APPEND);
}
foreach ($createTables as $createTable) {
    file_put_contents($databaseDiffFile, "\n" . $createTable . ";\n", FILE_APPEND);
}