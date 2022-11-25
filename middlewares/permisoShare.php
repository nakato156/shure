<?php

use Requests\Request;

class PermisoShare {

    public static function esCompartidoHash(Request $requests, Response $res, string $hash, ...$args) {
        global $BD;

        $data = $BD->Query("SELECT share, ruta FROM `storage` WHERE hash = ?", "s", [$hash]);
        if($data["share"]) $requests->next(array_merge(["path" => $data["ruta"]], $args));
        return $res->abort(401, "<h1>No autorizado</h1>");
    }

    public static function checkAnonimo(Request $requests, Response $res, string $hash, ...$args){
        global $BD;
        $data = $BD->Query("SELECT `users`.`username`, `storage`.`ruta` FROM `storage` INNER JOIN `users` ON `users`.`uuid` = `storage`.`user_id` WHERE hash_anonimo = ?", "s", [$hash]);
        if($data) {
            if(!$data["share"]) return $res->abort(401, "<h1>No autorizado</h1>");
            $path = DIRECTORY_SEPARATOR.$data["username"].$data["ruta"];
            $requests->next(["path" => $path]);
        }
        $res->abort(404, "<h1>No hay archivos</h1>");
    }
};

?>