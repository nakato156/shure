<?php
class StoreManager {
    public static function splitPath(string $path) : array {
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part || '..' == $part) continue;
            else $absolutes[] = $part;
        }
        return $absolutes;
    }

    public static function securePath(string $path) : string {
        return join(DIRECTORY_SEPARATOR, StoreManager::splitPath($path));
    }

    public static function crearDirectorio(string $path, string $raiz) : string {
        $splitPath = StoreManager::splitPath($path);
        $path = StoreManager::securePath($raiz);
        $relativePath = "";
        for($i = 0; $i < count($splitPath); $i++){
            $relativePath .= DIRECTORY_SEPARATOR.$splitPath[$i];
            $path .= DIRECTORY_SEPARATOR.$splitPath[$i];
            if(is_dir($path)) continue;
            mkdir($path);
        }
        return $relativePath;
    }

    public static function deleteDir(string $path) : bool {
        $path = StoreManager::securePath($path);
        if(is_dir($path)) return rmdir($path);
        return false;
    }

    public static function nombreSeguro(string $filename) : string {
        $info_file = pathinfo($filename);
        if(!array_key_exists("extension", $info_file)) return "";

        $filename = $info_file["filename"];
        $ext = $info_file["extension"];
    
        if(strlen($filename) == 1 || strlen($ext) == 1) return "";
        
        $nombre_seguro = "";
        for($i = 0; $i < strlen($filename); $i++) {
            $char = $filename[$i];
            if (ctype_alnum($char)) $nombre_seguro.= $char;
            else $nombre_seguro.= !empty($nombre_seguro) && $nombre_seguro[-1] == "_" ? "" : "_";
        }

        if($nombre_seguro[0] == "_") $nombre_seguro = substr($nombre_seguro, 1);
        if($nombre_seguro == "_" || empty($nombre_seguro)) return "";
        return "$nombre_seguro.$ext";
    }

    public static function formatFileSize(int $size): string {
        $mod = 1024;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $size > $mod; $i++) $size /= $mod;
        
        return round($size, 2).' '.$units[$i];
    }

    public static function eliminarFile(string $path): bool{
        $path = StoreManager::securePath($path);
        if(file_exists($path)) return unlink($path);
        return false;
    }

    public static function guardar(string $path, $file, string $filename) {
        $path = StoreManager::securePath($path);
        $filename = StoreManager::nombreSeguro($filename);
        if(!$filename) return false;
        return move_uploaded_file($file['tmp_name'], $path.DIRECTORY_SEPARATOR.$filename) ? $filename : false;
    }

    public static function enviar_img(string $path) {
        $path = StoreManager::securePath($path);
        
        if(!file_exists($path)) return false;
        $mime = mime_content_type($path);

        header("Content-type: $mime");
        readfile("$path");
    }

    public static function descargar(string $path, string $filename = null) {
        $path = StoreManager::securePath($path);
        $filename = $filename ? StoreManager::nombreSeguro($filename) : getFilename($path);
        
        if(!file_exists($path)) return false;
        $mime = mime_content_type($path);

        header("Content-disposition: attachment; filename=$filename");
        header("Content-type: $mime");
        readfile("$path");
        return true;
    }
};
?>