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




use Doctrine\Common\Collections\ArrayCollection;
use SN\ToolboxBundle\Helper\CommandHelper;

class RemoteBackupList
{
    protected $list;

    public function __construct($env, array $envConfig, $type = null)
    {
        $this->list = new ArrayCollection();

        $json_data = CommandHelper::executeRemoteCommand(sprintf("php bin/console sn:backup:get"), $envConfig[$env]);
        $json_data = json_decode($json_data, true);
        foreach ($json_data as $k => $v) {
            if (isset($v["type"]) && $type != null && $type != $v["type"]) {
                continue;
            }

            $backup = new Backup();
            $backup->setTimestamp($v["timestamp"]);
            $backup->setVersion($v["version"]);
            $backup->setCommit($v["commit"]);
            if (isset($v["type"])) {
                $backup->setType($v["type"]);
            }
            $this->list->add($backup);
        }
    }

    public function hasBackups()
    {
        return (count($this->list) > 0);
    }

    /**
     * @return array|Backup[]|ArrayCollection
     */
    public function getDumps(){
        return $this->list;
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