<?php
require("../conexion.php");

if (isset($_GET['term'])) {
    $term = $_GET['term'];
    $query = "SELECT codproducto, descripcion, precio FROM producto WHERE descripcion LIKE '%$term%' OR codigo LIKE '%$term%' LIMIT 10";
    $result = mysqli_query($conexion, $query);
    $productos = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode($productos);
}
?>
