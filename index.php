<?php
include 'db.php';

$id = $_GET['id']; // ❌ Vulnerabilidad: Inyección SQL (sin validación)
$query = "SELECT * FROM usuarios WHERE id = $id"; // SAST puede detectar uso directo de variables

$resultado = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($resultado);

echo "<h1>Bienvenido, " . $data['nombre'] . "</h1>"; // ❌ Vulnerabilidad: XSS si nombre no es sanitizado
