<?php
/**
 * SNBundle
 * Created by PhpStorm.
 * File: RemoteBackupList.php
 * User: thomas
 * Date: 08.03.17
 * Time: 16:32
 */

namespace SN\BackupBundle\Model;




use SN\ToolboxBundle\Helper\CommandHelper;

class RemoteBackupList
{
    protected $list;

    public function __construct($env, array $envConfig)
    {
        $json_data = CommandHelper::executeRemoteCommand(sprintf("php bin/console sn:backup:get"), $envConfig[$env]);
        $json_data = json_decode($json_data, true);
        foreach ($json_data["dumps"] as $k => $v) {
            $backup = new Backup();
            $backup->setDateTime($v["timestamp"]);
            $backup->setVersion($v["version"]);
            $backup->setCommit($v["commit"]);
            $json_data["dumps"][$k] = $backup;
        }
        $this->list = $json_data;
    }

    public function hasBackups()
    {
        return (count($this->list) > 0);
    }

    /**
     * @return array|Backup[]
     */
    public function getDumps(){
        return $this->list["dumps"];
    }

    public function jsonSerialize()
    {
        return $this->list;
    }

    public function __toString()
    {
        return json_encode($this);
    }
}