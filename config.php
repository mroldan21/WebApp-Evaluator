<?php
$host = 'localhost';
$dbname = 'prog2_tfpython';
$user = 'usuario_unlar';
$pass = 'Cachifor123Ñ';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Conexión fallida: " . $e->getMessage());
}
?>