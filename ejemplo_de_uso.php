<?php
include_once __DIR__ . "/Generador.php";
$generador = new Generador("localhost", "root", "", "mascotas");
# Ninguna tabla a ignorar
$generador->setTablasAIgnorar([]);

# ¡Vamos allá!
$generador->generar();
?>