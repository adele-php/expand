<?php

namespace app\components\spiderV2;

use app\models\TmpVolume;

class Volume {
    private $volume_id_map = [];

    /*
     * 得到卷的ID
     * @param $interface_bid
     * @param $volume_id 接口的卷ID  如果为0 表示接口没有提供卷
     */
    public function getVolumeId($interface_bid, $source, $volume_id = 0, $volume_name = '正文', $book_id = false) {
        if (isset($this->volume_id_map[$source][$interface_bid][$volume_id])) {
            return $this->volume_id_map[$source][$interface_bid][$volume_id];
        } else {
            $vid = TmpVolume::getVolumeId($interface_bid, $source, $volume_id, $volume_name, $book_id);
            $this->volume_id_map[$source][$interface_bid][$volume_id] = $vid;
            return $vid;
        }
    }

}

