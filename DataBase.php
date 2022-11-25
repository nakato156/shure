<?php

class BD {
    private $host, $bd_name, $user, $pass;
    public function __construct(string $host, string $bd_name, string $user, string $pass){
        $this->host = $host;
        $this->bd_name = $bd_name;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function Query(string $query, string $binding, array $values)
    {
        $mysqli = new mysqli($this->host, $this->user, $this->pass, $this->bd_name);
        $sentencia = $mysqli->prepare($query);
        $sentencia->bind_param($binding, ...$values);
        $sentencia->execute();
        $data = null;
        try {
            $data = $sentencia->get_result()->fetch_assoc();
        } catch (\Throwable $th) {}
        $mysqli->close();
        return $data === null ? true : $data;
    }
};

?>