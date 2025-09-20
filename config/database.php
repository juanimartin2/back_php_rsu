<?php
$host = "localhost";   // o "localhost"
$dbname = "prueba";
$username = "root";    // cambia si tu usuario MySQL es distinto
$password = "";        // pon tu contraseña si la tienes

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(["error" => "Error de conexión: " . $e->getMessage()]));
}
