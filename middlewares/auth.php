<?php

use Requests\Request;

class Auth {
    public function apiAuth($requests, Response $res, ...$args) {
        global $BD;
        $fecha_pago = null;
        $args_ = $requests->get("args");
        $fecha_actual = strtotime(date("d-m-Y H:i:00", time()));

        if(array_key_exists("token", $args_)){
            $token = $args_["token"];
            $data = $BD->query("SELECT username, uuid, caduca FROM users WHERE token = ?", "s", [$token]);
            $args["username"] = $data["username"];
            $args["uuid"] = $data["uuid"];
                
            if($data) $fecha_pago = strtotime($data['caduca']);
            else $res->abort(401, ['auth' => false]);
        } else if(isset($_SESSION['user'])){
            $user = $_SESSION['user'];
            $query = $BD->query("SELECT caduca FROM users WHERE uuid = ?", "s", [$user['id']]);
            
            $fecha_pago = strtotime($query['caduca']);
            $_SESSION['user']['caduca'] = $query['caduca'];
            $args = array_merge($args, ['uuid' => $user['id'], 'username' => $user['username']]);
            
            if($fecha_actual > $fecha_pago) $res->abort(401, ["msg" => "La subscripcion no se ha renovado"]);
        }
        else $res->abort(401, [ "auth" => false ]);
        
        if($fecha_actual > $fecha_pago) $res->abort(401, ["msg" => "La subscripcion no se ha renovado"]);
        $requests->next($args);
    }

    public static function checkCaduca(Request $requests, Response $res, ...$args){
        global $BD;
        $username = $args["username"];
        $data = $BD->Query("SELECT * FROM users WHERE username = ?", "s", [$username]);
        if($data && $data["active"]) {
            $fecha_pago = strtotime($data['caduca']);
            $fecha_actual = strtotime(date("d-m-Y H:i:00", time()));
            if($fecha_actual > $fecha_pago) return $res->abort(401, "<h1>Se ha denegado el acceso al recurso</h1>");            
            $requests->next($args);
        }
        else return $res->abort(404, "<strong><h1>Not Found 404</h1></strong>");
    }

    public static function requireSesion($requests, Response $res, ...$args) {
        if(isset($_SESSION['user'])) return $requests->next(...$args);
        $res->abort(401);
    }
};

?>