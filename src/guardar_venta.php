<?php
session_start();
include "../conexion.php";
$id_user = $_SESSION['idUser'];
$permiso = "laboratorios";
$sql = mysqli_query($conexion, "SELECT p.*, d.* FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso WHERE d.id_usuario = $id_user AND p.nombre = '$permiso'");
$existe = mysqli_fetch_all($sql);
if (empty($existe) && $id_user != 1) {
    header('Location: permisos.php');
}

// Verificar que se recibe una solicitud POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener los datos enviados
    $id_cliente = intval($_POST['idcliente']);
    $total = floatval($_POST['total']);
    $productos = json_decode($_POST['productos'], true); // Decodificar el JSON de productos

    // Comprobar que los datos son válidos
    if (empty($id_cliente) || $total <= 0 || empty($productos)) {
        echo json_encode(['status' => 'error', 'message' => 'Datos inválidos.']);
        exit;
    }

    // Iniciar la transacción
    $conn->beginTransaction();

    try {
        // Insertar la venta en la tabla 'ventas'
        $stmt = $conn->prepare("INSERT INTO ventas (id_cliente, total, id_usuario) VALUES (?, ?, ?)");
        $id_usuario = 1; // Reemplaza con el ID del usuario correspondiente
        $stmt->execute([$id_cliente, $total, $id_usuario]);

        // Obtener el ID de la última venta insertada
        $id_venta = $conn->lastInsertId();

        // Insertar los productos vendidos en una tabla intermedia (debes crear esta tabla si no existe)
        // Asumiendo que tienes una tabla 'venta_producto' que relaciona ventas y productos
        $stmt = $conn->prepare("INSERT INTO venta_producto (id_venta, codproducto, cantidad) VALUES (?, ?, ?)");
        
        foreach ($productos as $producto) {
            $codproducto = $producto['codproducto']; // Asegúrate de que el objeto tenga este campo
            $cantidad = intval($producto['cantidad']); // Cantidad vendida
            $stmt->execute([$id_venta, $codproducto, $cantidad]);
            
            // Actualizar la existencia del producto en la tabla 'producto'
            $stmt = $conn->prepare("UPDATE producto SET existencia = existencia - ? WHERE codproducto = ?");
            $stmt->execute([$cantidad, $codproducto]);
        }

        // Confirmar la transacción
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Venta generada exitosamente.']);
    } catch (Exception $e) {
        // Deshacer la transacción en caso de error
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Error al generar la venta: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
}
?>
