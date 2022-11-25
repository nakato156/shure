<?php
class Share {
    public static function initShare(string $path, string $user_path, string $user_id) : bool {
        global $BD;
        $hash = hash_file($_ENV["ALGH"], $path);
        $share = $BD->Query("SELECT share FROM `storage` WHERE hash = ?", "s", [$hash]);
        if(empty($share)){
            $uuid = uuidv4();
            return boolval(!$BD->Query("INSERT INTO `storage` (uuid, user_id, ruta, hash, share) VALUES (?, ?, ?, ?, ?)", "ssssi" ,[$uuid, $user_id, $user_path, $hash, 1]));
        }else if($share && $share["share"] === 0) {
            return boolval(!$BD->Query("UPDATE `storage` SET share = ? WHERE hash = ?", "is", [1, $hash]));
        }
        return true;
    }

    public static function stopShare(string $path){
        global $BD;
        $hash = hash_file($_ENV["ALGH"], $path);
        if($BD->Query("SELECT share FROM `storage` WHERE hash = ?", "s", [$hash])) {
            return $BD->Query("UPDATE `storage` SET share = ? WHERE hash = ?", "is", [0, $hash]);
        }
    }
};
?>