<?php
require_once __DIR__ . "/../config/database.php";

$nombre = "Javier";
$email = "javier@mail.com";
$passwordPlano = "123456";
$hash = password_hash($passwordPlano, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO usuarios (nombre,email,password) VALUES (?,?,?)");
$stmt->execute([$nombre,$email,$hash]);

echo "Usuario creado con éxito";
