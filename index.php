<?php
session_start();
require_once('./vendor/autoload.php');
require_once('./modules/CSRF.php');
require_once('./modules/shareFile.php');
require_once('./modules/Almacenamiento.php');
require_once('./helpers/funciones.php');
require_once('./Http/routes.php');
require_once('DataBase.php');

use Requests\Request;
use routes\Router;
use StoreManager as SM;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$route = new Router();
$BD = new BD($_ENV["HOST_BD"], $_ENV["NAME_BD"], $_ENV["USER_BD"], $_ENV["PASS_BD"]);

$route->get('/', function(Request $requests, Response $res){
    $res->render("index.twig");
});

$route->get('/login', function (Request $requests, Response $res) {
    if(!isset($_SESSION['user'])) return $res->render("login.twig");
    $username = $_SESSION['user']['username'];
    header("Location: /perfil/$username");
});

$route->post('/checklogin', function (Request $requests, Response $res) {
    global $BD;
    $data = $requests->get('data');
    $username = $data['username'];
    $pass = $data['password'];
    $data = $BD->Query("SELECT * FROM `users` INNER JOIN `planes` ON `planes`.id = `users`.rol WHERE `users`.username = ?", "s", [$username]);

    $status = $data == true;
    if($data && password_verify($pass, $data["password"])){
        $hoy = new DateTime(date("Y-m-d H:i:s"));
        $caduca = new DateTime(date("Y-m-d H:i:s", strtotime($data["caduca"])));
        $_SESSION["caduca"] = $data["caduca"];
        $vencido = $hoy > $caduca;
        $dias_restantes = $vencido ? "vencido" : $hoy->diff($caduca)->days;

        $_SESSION['user'] = [
            'cuenta_activa' => $data['active'],
            'vencido'   => $vencido,
            'id'        => $data['uuid'],
            'email'     => $data['email'],
            'username'  => $data['username'],
            'rol'       => $data['rol'],
            'foto'      => $data["username"].".png",
            'plan'      => $data['name_plan'],
            'costo_plan'=> $data['costo'],
            'caduca'    => $caduca->format("Y-m-d"),
            'dias_restantes' => $dias_restantes,
        ];
    }

    $res->json(["status"=> $status]);
});

$route->get('/registro', function(Request $requests, Response $res) {
    if(isset($_SESSION["user"])) header("Location: /perfil/".$_SESSION["user"]["username"]);
    global $BD;

    $_SESSION["uuid"] = uuidv4();
    $args = $requests->get("args");
    if(!in_array("tipo", $args) || $args["tipo"] != "basico" && $args["tipo"] != "normal"){
        $name_plan = "normal";
    }else $name_plan = $args["tipo"];
    $data_plan = $BD->Query("SELECT `name_plan`, `costo` FROM `planes` WHERE name_plan = ?", "s", [$name_plan]);
    
    $_SESSION["plan"] = $name_plan;
    $_SESSION["valor"] = $data_plan["costo"];
    $res->render('registro.twig', [
        "plan" => $data_plan['name_plan'], 
        "value"=> $data_plan['costo'], 
    ]);
});

$route->post("/createOrder", function(Request $requests, Response $res) {
    if(isset($_SESSION["user"])) {
        session_destroy();
        session_start();
        $res->abort(401); 
    }

    global $BD;
    $data = $requests->get("data");

    CSRF::verificar( $requests->get("X-ACCESS-TOKEN") );

    $username = $data["username"];
    $email = $data["email"];

    if(empty($username) || empty($email) || empty($data["password"])) {
        $res->json(["error" => "datos invalidos", "msg" => "llene todos los campos"]);
        $res->abort(400);
    }

    if($BD->Query("SELECT `username` FROM `users` WHERE `username` = ? OR `email` = ?", "ss", [$username, $email])){
        $res->json(["error"=>"Datos existentes", "msg"=>"El nombre de usuario o email ya ha sido registrado"]);
        return;
    }

    $plan = $_SESSION["plan"];
    $valor = $_SESSION["valor"];
    $uuid = $_SESSION["uuid"];

    $info_pay = [
        "intent" => "CAPTURE",
        "purchase_units" => [
            [
                'reference_id' => "$uuid",
                "amount" => [
                    "description" => "Contratacion de plan $plan",
                    "currency_code" => "USD",
                    "name" => "$plan",
                    "value" => $valor,
                ]
            ]
        ],
    ];

    $info = createOrder($info_pay, $uuid);
    
    $_SESSION["createOrder"] = [
        "username" => $username,
        "email" => $email,
        "password" => password_hash($data["password"], PASSWORD_DEFAULT)
    ];

    $res->json(["status" => true, "data" => $info]);
});

$route->post('/createAccount/:id', function(Request $requests, Response $res, $id){
    if(isset($_SESSION["user"])) $res->abort(401);

    global $BD;
    CSRF::verificar( $requests->get("X-ACCESS-TOKEN") );
    $plan = $_SESSION["plan"];
    
    $orderData = checkOrder($id);

    $errorDetail = (array_key_exists("details", $orderData) && is_array($orderData["details"])) && $orderData["details"][0];
    if($errorDetail && $errorDetail["issue"] === 'INSTRUMENT_DECLINED') {
        $res->json(["declined" => true]); return;
    }
    else if ($errorDetail) {
        $res->json(["error" => true, "info" => $orderData]); return;
    }

    $data_plan = $BD->Query("SELECT * FROM `planes` WHERE name_plan = ?", "s", [$plan]);
    $rol = $data_plan["id"];
    $costo = $data_plan["costo"];

    $uuid = $orderData["purchase_units"][0]["reference_id"];
    $username = $_SESSION["createOrder"]["username"];
    $email = $_SESSION["createOrder"]["email"];
    $password = $_SESSION["createOrder"]["password"];

    $BD->Query("INSERT INTO `users` (`uuid`, `username`, `email`, `password`, `rol`) VALUE (?,?,?,?,?)", "sssss", [$uuid, $username, $email, $password, $rol]);
    
    $hoy = new DateTime(date("Y-m-d H:i:s"));
    $caduca = new DateTime(date("Y-m-d")."+ 1 month");
    $dias_restantes = $caduca->diff($hoy);

    $_SESSION["user"] = [
        'id'        => $uuid,
        'username'  => $username,
        'rol'       => $rol,
        'foto'      => "default",
        'plan'      => $plan,
        'costo_plan'=> $costo,
        'caduca'    => $hoy->format("Y-m-d"),
        'dias_restantes' => $dias_restantes->days,
    ];
    mkdir("./storage/$username");
    $orderData["username"] = $username;
    $res->json($orderData);
});

$route->get('/perfil/:username', function(Request $requests, Response $res, $username){
    global $BD;

    if(!isset($_SESSION['user'])) return header("Location: /login");
    
    $user = $_SESSION['user'];
    if(array_key_exists("merchant_id", $user)){
        $data = $BD->Query("SELECT `users`.rol, `planes`.costo, `planes`.name_plan FROM `users` INNER JOIN `planes` ON `users`.rol = `planes`.id WHERE uuid = ?", "s", [$user['id']]);
        $_SESSION['user']['rol'] = $data['rol'];
        $_SESSION['user']['plan'] = $data['name_plan'];
        $_SESSION['user']['costo_plan'] = $data['costo'];
        unset($_SESSION['user']['merchant_id']);
    }

    if($_SESSION['user']['rol'] == 1) {
        $res->render('noInterface.twig', ["username" => $user['username']]); return;
    }
    
    $username = $user['username'];
    $res->render('perfil.twig', ["username" => $username]);
});

$route->get('/perfil/:username/pago', function(Request $requests, Response $res, $username){
    if(!isset($_SESSION['user'])) header('Location: /login');
    global $twig, $BD;
    
    $fecha = $BD->Query("SELECT `caduca` FROM `users` WHERE uuid = ?", "s", [$_SESSION['user']['id']]);
    $fecha_caduca = strtotime($fecha['caduca']);
    $fecha_actual = strtotime(date("d-m-Y H:i:00", time()));
    if($fecha_actual < $fecha_caduca) { 
        $res->render("notPermissPay.html");
        return;
    }

    $user = $_SESSION['user'];
    $data = ['value'=> $user['costo_plan'], 'service_name'=> $user['plan'], 'basico' => $user['plan'] == 'basico', 'user_id'=>$user['id']];

    $args = $requests->get("args");
    if($args) {
        $plan = $BD->Query("SELECT * FROM `planes` WHERE name_plan = ?", "s", [$args['tipo']]);

        $data['value'] = $plan ? $plan['costo'] : 4;
        $data['service_name'] = $plan ? $plan['name_plan'] : "normal";
        $data['basico'] = $plan ? $plan['name_plan'] == 'basico' : false;
    } 
    $data['alert'] = $user['plan'] != $data['service_name'];
    $res->render('pago.html', $data);
});

$route->post('/payment-notif', function(Request $requests, Response $res) {
    global $BD;

    $data = $requests->get("data");
    $resource = $data["resource"];
    $order_id = $resource['id'];
    
    
    if($resource['status'] != "COMPLETED"){
        $file = fopen('datos.log', 'a+b');
        fwrite($file, json_encode($data)."\n");
        fclose($file);
        return;
    }
    
    $create_time = $resource["create_time"];
    
    $purchase = $resource['purchase_units'][0];
    $user_id = $purchase['reference_id'];
    $monto = floatval($purchase['amount']['value']);
    $currency_code = $purchase['amount']['currency_code'];

    $info_payer = $purchase['payee'];
    $email = $info_payer['email_address'];
    $merchand_id = $info_payer['merchant_id'];
    
    $fullname = $purchase['shipping']['name']["full_name"];
    $payments = $purchase['payments']['captures'][0];
    $payment_id = $payments['id'];
    $status = $payments['status'];
    $monto_neto = floatval($payments['seller_receivable_breakdown']['net_amount']['value']);
    
    $country_code = $resource['payer']['address']['country_code'];
    $payer_id = $resource['payer']['payer_id'];
    
    $BD->Query("INSERT INTO pagos (`user_id`,`order_id`, `full_name`, `email`, `merchant_id`, `monto`, `monto_neto`, `status`, `payer_id`, `country_code`, `create_time`, `currency_code`, `payment_id`) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)", "sssssddssssss",[
        $user_id, $order_id, $fullname, $email, $merchand_id, $monto, $monto_neto, $status, $payer_id, $country_code, $create_time, $currency_code, $payment_id
    ]);
    
    $fecha_actual = date("Y-m-d", strtotime($create_time));
    $fecha_caduca = date("Y-m-d",strtotime($fecha_actual."+ 1 month"));
    $id = $BD->Query("SELECT id FROM `planes` WHERE costo = ?", "i", [intval($monto)]);
    $BD->Query("UPDATE `users` SET `ultimo_pago` = ?, `caduca` = ?, rol = ?, active = ?", "ssii", [$create_time, $fecha_caduca, $id['id'], 1]);
});

$route->post('/update', function(Request $requests, Response $res){
    if(!isset($_SESSION['user'])) $res->abort(401);
    $_SESSION['user']['merchant_id'] = $requests->get("data")['merchant_id'];
});

$route->get("/img/:username/:hash", "PermisoShare::esCompartidoHash", "Auth::checkCaduca", function(Request $requests, Response $res, $username, $path){

    $img = "./storage/$username".$path;

    if(file_exists($img)) return SM::enviar_img($img);
    return $res->abort(404, "<strong><h1>Not Found 404</h1></strong>");
});

$route->get("/img/shure/:hash", "PermisoShare::checkAnonimo", function(Request $requests, Response $res, string $path){
    $img = "./storage".$path;
    if(file_exists($img)) return SM::enviar_img($img);
    return $res->abort(404, "<strong><h1>Not Found 404</h1></strong>");
});

$route->get('/fragment/:fragName', function(Request $requests, $res, $fragName){
    if(isset($_SESSION['user'])){
        $data = $_SESSION["user"];
        $path_user = __DIR__ . "/storage/".$data['username'];
        $data['files'] = new FilesystemIterator($path_user, FilesystemIterator::SKIP_DOTS);
        
        if($fragName == "dashboard") {
            $total_size = 0;
            
            $args = $requests->get("args");
            if(array_key_exists("dir", $args)) $path_user.= $args["dir"];
            
            $info = getStatsTotalFiles($path_user);
            $data["cant_imgs"] = $info["cant"];
            $total_size = $info["size"];

            $data['size'] = SM::formatFileSize($total_size);
        }
        $res->render("fragments/$fragName.twig", $data);
    }else $res->text("<h1>Sesion expirada</h1>");
});

$route->post('/update/infoUser', "Auth::apiAuth", function(Request $requests, Response $res, ...$args){
    global $BD;
    
    $username = $args["username"];
    $uuid = $args["uuid"];

    
    $data = $requests->get("data");
    $nuevo_username = $data["username"];
    $nuevo_email = $data["email"];

    $user = $_SESSION['user'];
    $nueva_foto = $requests->get("files");
    if(!$nueva_foto && $username == $user['username'] && $nuevo_email == $user['email']) return $res->json(["status" => null]);
    // if($nueva_foto) $nueva_foto = imagescale($nueva_foto, 64, 64);

    if($nueva_foto) {        
        $file_tmp = $nueva_foto["ftPerfil"]['tmp_name'];
        if(is_uploaded_file($file_tmp)){
            $ruta_destino =  SM::securePath("./static/img/perfil/$username.png");
            move_uploaded_file($file_tmp, $ruta_destino);
        }
    }
    
    $BD->Query("UPDATE `users` SET `username` = ?, `email` = ? WHERE `uuid` = ? ", "sss", [$nuevo_username, $nuevo_email, $uuid]);
    $_SESSION['user']['username'] = $nuevo_username;
    $_SESSION['user']['email'] = $nuevo_email;

    return $res->json(["status" => true]);
});

$route->post('/changepassword', "Auth::apiAuth", function(Request $request, Response $res, ...$args){
    global $BD;
    
    $uuid = $args['uuid'];
    $data = $request->get("data");
    $old_pass = $data["oldpassword"];
    $nueva_pass = $data["newpassword"];
    $re_nueva_pass = $data["renewpassword"];
    
    if($nueva_pass == $old_pass) return $res->json(["status" => false, "error" => "La nueva contraseña no puede ser igua a la anterior"]);
    else if($nueva_pass != $re_nueva_pass) return $res->json(["status" => false, "error" => "Las contraseñas no coinciden"]);
    $query = $BD->Query("SELECT `password` FROM `users` WHERE uuid = ?", "s", [$uuid]);
    if(!password_verify($old_pass, $query['password'])) return $res->json(["status" => false, "error" => "La contraseña no es igual a la anterior"]);

    $encrypt_pass = password_hash($nueva_pass, PASSWORD_DEFAULT);
    $BD->Query("UPDATE `users` SET `password` = ? WHERE uuid = ?", "ss", [$encrypt_pass, $uuid]);
    return $res->json(["status" => true]);

});

$route->get('/salir', function (Request $requests, Response $res) {
    session_destroy();
    $res->redirect('/');
});

// routes api
$route->get("/api/v1/info-file", "Auth::apiAuth", function(Request $requests, Response $res, ...$args){
    $data = $requests->get("args");
    $username = $args["username"];
    if(!$data || !array_key_exists("file", $data)) return $res->abort(400);
    $ruta = SM::securePath(__DIR__."/storage/$username/".$data["file"]);
    $info_file = [
        "size" => SM::formatFileSize(filesize($ruta)),
        "nombre" => getFilename($ruta),
        "modificacion" => date("F d Y H:i:s", filectime($ruta))
    ];
    $res->json($info_file);
});

$route->post('/api/v1/folder', "Auth::apiAuth", function(Request $requests, Response $res, $username, ...$args){
    $body = $requests->get("data");
    if($body && array_key_exists("name", $body)){
        $name = $body["name"];
        $BasePath = __DIR__."/storage/$username/";
        $inputPath = ((array_key_exists("path", $body) ? $body["path"] : "")."/$name");
        try {
            $path = SM::crearDirectorio($inputPath, $BasePath);
            return $res->json(["status" => true, "path" => $username.$path]);
        } catch (\Throwable $th) {
            return $res->json(["status" => false, "msg" => "no se ha podido crear el directorio"]);
        }  
    }
});

$route->post('/api/v1/folder/list', "Auth::apiAuth", function(Request $requests, Response $res, $username, ...$args){
    $data = $requests->get("data");
    $dir = trim($data && array_key_exists("dir", $data) ? $data["dir"] : "");

    $path_user = SM::securePath("./storage/$username/$dir");
    if(!is_dir($path_user)) return $res->json(["status" => false, "msg" => "no existe el directorio"]);
    
    $files = new FilesystemIterator($path_user, FilesystemIterator::SKIP_DOTS);
    $response = ["dirname"=> $dir ? $dir : $username, "files"=>[]];
    $total_size = 0;

    foreach ($files as $file) {
        $response['files'][] = ["name" => $file->getFilename(), "isDir"=>$file->isDir(), "isFile"=>$file->isFile()];
        $total_size += $file->getSize();
    }
    $response['size'] = $total_size;
    $res->json($response);
});

$route->delete('/api/v1/folder', "Auth::apiAuth", function(Request $requests, Response $res, $username, ...$args){
    $body = $requests->get("data");
    if($body && array_key_exists("name", $body)){
        $name = $body["name"];
        $BasePath = __DIR__."/storage/$username/";
        $inputPath = $BasePath.(array_key_exists("path", $body) ? $body["path"] : "")."/$name";
        
        if(SM::deleteDir($inputPath)) return $res->json(["status" => true]);
        return $res->json(["status" => false, "msg" => "no se ha podido crear el directorio"]); 
    }
});

$route->post('/api/v1/upload', "Auth::apiAuth", function(Request $requests, Response $res, $username, ...$args){
    $body = $requests->get("data");
    $files = $requests->get("files");
    
    if(empty($files)) return $res->abort(400);
    $BasePath = __DIR__."/storage/$username/";
    $path = $BasePath.(($body && array_key_exists("path", $body) ? $body["path"] : ""));
    
    $files_guardados = [];

    foreach ($files as $key => $file) {
        $filename = isset($body["filename"]) ? $body["filename"] : $file["name"];
        if(!SM::guardar($path, $file, $filename))
            return $res->abort(400, ["status" => false, "msg" => "No se ha podido guardar el archivo $filename"]);
        $files_guardados[] = ["filename" => $filename];
    }
    return $res->json($files_guardados);
});

$route->post('/api/v1/compartir', "Auth::apiAuth", function(Request $requests, Response $res, $username, $uuid){
    $body = $requests->get("data");

    if(!array_key_exists("filename", $body)) return $res->abort(400, ["error" => true, "msg" => "No se ha especificado un archivo"]);
    $user_path = DIRECTORY_SEPARATOR.SM::securePath($body["filename"]);
    $path = join(DIRECTORY_SEPARATOR, [__DIR__, "storage", $username, $user_path]);
    $info = Share::initShare($path, $user_path, $uuid);
    $res->json(["status" => $info]);
});

$route->get("/api/v1/download", "Auth::apiAuth", function(Request $requests, Response $res, ...$args){
    $data = $requests->get("args");
    if(!$data || !array_key_exists("file", $data)) return $res->abort(400);
    
    $ruta_file = $data["file"];
    $username = $args["username"];
    SM::descargar(__DIR__."/storage/$username/$ruta_file");
});

$route->get('/api/v1/download/:hash', "Auth::apiAuth", function(Request $requests, Response $res, ...$args){
    global $BD;
    $data = $BD->Query("SELECT ruta FROM `storage` WHERE hash = ?", "s", [$args["hash"]]);
    $ruta = join(DIRECTORY_SEPARATOR, [__DIR__, "storage", $args["username"], $data["ruta"]]);

    $descarga = SM::descargar($ruta);
    if(!$descarga) { $res->render("noImg.html"); }
});

$route->delete('/api/v1/delete', "Auth::apiAuth", function(Request $requests, Response $res, ...$args){
    $data = $requests->get("data");
    $username = $args["username"];
    $file = $data["file"];
    $status = SM::eliminarFile(__DIR__."/storage/$username/$file");
    return $res->json(["status" => $status]);
});

$route->run();
?>