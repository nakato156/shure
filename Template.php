<?php 
namespace Template;

class Template {
    public static function parse(string $filename, array $vars){
        if(substr($filename, -3) == "php"){
            return include_once($filename);
        }
        if ($file = fopen($filename, "r")) {
            while(!feof($file)) {
                $line = fgets($file);

                preg_match_all('/{%\s{0,}\$([a-zA-Z_]?[0-9_]?)+\s{0,}%}/', $line, $matches, PREG_OFFSET_CAPTURE);
                if($matches[0]){
                    foreach ($matches[0] as $value) {
                        if($value[0]){
                            $varname = substr(trim(substr($value[0], 2, -2)), 1);
                            if(array_key_exists($varname, $vars)){
                                $var = $vars[$varname];
                                $line = str_replace($value[0], $var, $line);
                                if(strpos($line, '{%') === false) break;
                            }
                        }
                    }
                }
                echo $line;
            }
            fclose($file);
        }   
    }
}
?>