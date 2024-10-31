<?php
session_start();
require("../conexion.php");
$id_user = $_SESSION['idUser'];
$permiso = "nueva_venta";

$sql = mysqli_query($conexion, "SELECT p.*, d.* FROM permisos p INNER JOIN detalle_permisos d ON p.id = d.id_permiso WHERE d.id_usuario = $id_user AND p.nombre = '$permiso'");
$existe = mysqli_fetch_all($sql, MYSQLI_ASSOC);

if (empty($existe) && $id_user != 1) {
    header('Location: permisos.php');
    exit();
}
$result = mysqli_query($conexion, "SELECT * FROM producto WHERE existencia > 0");
$productos = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (isset($_GET['term'])) {
    $term = $_GET['term'];
    $query = "SELECT codproducto AS id, descripcion, precio FROM producto WHERE descripcion LIKE '%$term%' OR codigo LIKE '%$term%' LIMIT 10";
    $result = mysqli_query($conexion, $query);
    $productos = mysqli_fetch_all($result, MYSQLI_ASSOC);
    echo json_encode($productos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = $_POST['idcliente'];
    $total = $_POST['total'];
    $productos = json_decode($_POST['productos'], true); // Decodificar JSON

    if (empty($id_cliente) || empty($total) || !is_array($productos) || count($productos) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Uno o más campos no tienen valores válidos.']);
        exit;
    }

    // Comenzar transacción
    mysqli_begin_transaction($conexion);

    try {
        // Insertar la venta en la tabla de ventas
        $sql_venta = "INSERT INTO ventas (id_cliente, total, fecha) VALUES (?, ?, NOW())"; // Asegúrate de tener la tabla 'ventas' creada
        $stmt_venta = mysqli_prepare($conexion, $sql_venta);
        mysqli_stmt_bind_param($stmt_venta, "sd", $id_cliente, $total);
        mysqli_stmt_execute($stmt_venta);
        $id_venta = mysqli_insert_id($conexion); // Obtener el ID de la venta recién insertada

        // Insertar cada producto relacionado con la venta
        foreach ($productos as $producto) {
            $codproducto = $producto['codproducto'];
            $cantidad = $producto['cantidad'];
            $precio = $producto['precio'];
            $subtotal = $cantidad * $precio;

            $sql_detalle = "INSERT INTO detalle_ventas (id_venta, codproducto, cantidad, precio, subtotal) VALUES (?, ?, ?, ?, ?)";
            $stmt_detalle = mysqli_prepare($conexion, $sql_detalle);
            mysqli_stmt_bind_param($stmt_detalle, "iisds", $id_venta, $codproducto, $cantidad, $precio, $subtotal);
            mysqli_stmt_execute($stmt_detalle);

            // Actualizar la existencia del producto
            $sql_actualizar = "UPDATE producto SET existencia = existencia - ? WHERE codproducto = ?";
            $stmt_actualizar = mysqli_prepare($conexion, $sql_actualizar);
            mysqli_stmt_bind_param($stmt_actualizar, "is", $cantidad, $codproducto);
            mysqli_stmt_execute($stmt_actualizar);
        }

        // Confirmar la transacción
        mysqli_commit($conexion);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } finally {
        mysqli_stmt_close($stmt_venta);
        mysqli_stmt_close($stmt_detalle);
        mysqli_close($conexion);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
}

include_once "includes/header.php";
?>

<div class="row">
    <div class="col-lg-12">
        <div class="form-group">
            <h4 class="text-center">Datos del Cliente</h4>
        </div>
        <div class="card">
            <div class="card-body">
                <form method="post" id="form-venta">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                            <input type="hidden" id="idcliente" value="1" name="idcliente" required>
                                <label>Nombre</label>
                                <input type="text" name="nom_cliente" id="nom_cliente" class="form-control" placeholder="Ingrese nombre del cliente" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="number" name="tel_cliente" id="tel_cliente" class="form-control" disabled required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" name="dir_cliente" id="dir_cliente" class="form-control" disabled required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-primary text-white text-center">Buscar Productos</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-5">
                        <div class="form-group">
                            <label for="producto">Código o Nombre</label>
                            <input id="producto" class="form-control" type="text" name="producto" placeholder="Ingresa el código o nombre" onkeyup="buscarProducto()">
                            <ul id="lista_productos" class="list-group" style="position: absolute; z-index: 1000;"></ul>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="form-group">
                            <label for="cantidad">Cantidad</label>
                            <input id="cantidad" class="form-control" type="text" name="cantidad" placeholder="Cantidad" onkeyup="calcularPrecio(event)">
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="form-group">
                            <label for="precio">Precio</label>
                            <input id="precio" class="form-control" type="text" name="precio" placeholder="Precio" disabled>
                        </div>
                    </div>
                    <div class="col-lg-2">
                        <div class="form-group">
                            <label for="sub_total">Sub Total</label>
                            <input id="sub_total" class="form-control" type="text" name="sub_total" placeholder="Sub Total" disabled>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="id_producto">
                <button type="button" class="btn btn-primary" onclick="agregarProducto()">Agregar Producto</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover" id="tblDetalle">
                <thead class="thead-dark">
                    <tr>
                        <th>Id</th>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Sub Total</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="detalle_venta">
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold">
                        <td>Total Pagar</td>
                        <td id="total_pagar">0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="col-md-6">
            <button type="button" class="btn btn-primary" onclick="generarVenta()">Generar Venta</button>
        </div>
    </div>
</div>

<script>
    let productosSeleccionados = [];

    function agregarProducto() {
        const codproducto = document.getElementById('id_producto').value;
        const descripcion = document.getElementById('producto').value;
        const cantidad = parseInt(document.getElementById('cantidad').value);
        const precio = parseFloat(document.getElementById('precio').value);

        // Verificar los valores de los campos
        if (!codproducto || !descripcion || !cantidad || isNaN(cantidad) || isNaN(precio)) {
            console.error("Uno o más campos no tienen valores válidos.");
            alert("Por favor, completa todos los campos correctamente.");
            return;
        }

        const subtotal = cantidad * precio;
        const producto = {
            codproducto,
            descripcion,
            cantidad,
            precio
        };
        productosSeleccionados.push(producto);

        const fila = `
        <tr>
            <td>${codproducto}</td>
            <td>${descripcion}</td>
            <td>${cantidad}</td>
            <td>${precio.toFixed(2)}</td>
            <td>${subtotal.toFixed(2)}</td>
            <td><button onclick="eliminarProducto(this)">Eliminar</button></td>
        </tr>
    `;

        document.getElementById('detalle_venta').innerHTML += fila;
        actualizarTotal();
    }

    function generarVenta() {
        const id_cliente = document.getElementById('id_cliente').value; // ID del cliente
        const total = parseFloat(document.getElementById('total_pagar').innerText); // Total calculado

        // Mostrar los valores en la consola para depuración
        console.log('ID Cliente:', id_cliente);
        console.log('Total:', total);
        console.log('Productos Seleccionados:', productosSeleccionados);

        // Validar los campos necesarios
        if (!id_cliente || isNaN(id_cliente) || !total || total <= 0 || productosSeleccionados.length === 0) {
            console.error("Faltan datos para generar la venta.");
            alert("Uno o más campos no tienen valores válidos. Por favor, completa todos los campos necesarios para generar la venta.");
            return;
        }

        const data = new FormData();
        data.append('idcliente', id_cliente);
        data.append('total', total);
        data.append('productos', JSON.stringify(productosSeleccionados)); // Agregar productos

        fetch('guardar_venta.php', {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success') {
                    alert('Venta generada exitosamente.');
                    // Limpiar la tabla y el total después de la venta exitosa
                    document.getElementById('detalle_venta').innerHTML = '';
                    actualizarTotal();
                    // Opcional: Resetear campos del formulario
                    document.getElementById('id_cliente').value = '';
                    document.getElementById('nom_cliente').value = '';
                } else {
                    alert('Error al generar la venta: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function actualizarTotal() {
        const total = productosSeleccionados.reduce((sum, producto) => sum + (producto.cantidad * producto.precio), 0);
        document.getElementById('total_pagar').innerText = total.toFixed(2);
    }

    function eliminarProducto(boton) {
        const fila = boton.closest("tr");
        const codproducto = fila.cells[0].innerText;
        productosSeleccionados = productosSeleccionados.filter(p => p.codproducto != codproducto);

        actualizarTabla();
        actualizarTotal();
    }

    function buscarProducto() {
        const term = document.getElementById('producto').value;
        const lista = document.getElementById('lista_productos');

        if (term.length < 2) {
            lista.innerHTML = '';
            return;
        }

        fetch(`buscar_productos.php?term=${term}`)
            .then(response => response.json())
            .then(data => {
                lista.innerHTML = '';

                data.forEach(producto => {
                    lista.innerHTML += `<li class="list-group-item" onclick="seleccionarProducto('${producto.id}', '${producto.descripcion}', ${producto.precio})">${producto.descripcion} - $${producto.precio}</li>`;
                });
            });
    }

    function seleccionarProducto(id, descripcion, precio) {
        document.getElementById('id_producto').value = id;
        document.getElementById('producto').value = descripcion;
        document.getElementById('precio').value = precio;
        document.getElementById('cantidad').value = 1;
        document.getElementById('lista_productos').innerHTML = '';
    }

    function calcularPrecio(event) {
        const cantidad = parseInt(event.target.value);
        const precio = parseFloat(document.getElementById('precio').value);

        if (cantidad && !isNaN(precio)) {
            document.getElementById('sub_total').value = (cantidad * precio).toFixed(2);
        } else {
            document.getElementById('sub_total').value = '0.00';
        }
    }
</script>

<?php include_once "includes/footer.php"; ?>