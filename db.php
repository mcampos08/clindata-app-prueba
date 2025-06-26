<?php
// ❌ Vulnerabilidad: credenciales expuestas
$conn = mysqli_connect('localhost', 'root', '123456', 'clindata');
