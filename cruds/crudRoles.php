<?php
// Asegúrate de que este archivo contenga la conexión a MySQLi
// Por ejemplo:
// $conn = new mysqli("localhost", "usuario", "contraseña", "nombre_base_datos");
// if ($conn->connect_error) {
//     die("Error de conexión: " . $conn->connect_error);
// }
include '../conexion.php'; // Asume que $conn es una instancia de mysqli

class RolManager {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
        // Configurar el modo de reporte de errores de MySQLi
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    private function validarNombre($nombre) {
        return preg_match("/^[a-zA-Z0-9\- ]+$/", $nombre) && strlen($nombre) > 0 && strlen($nombre) <= 250;
    }

    /**
     * Verifica si un rol está siendo utilizado por algún empleado.
     * Asume una tabla pivote 'empleado_rol'.
     * @param int $rolId El ID del rol a verificar.
     * @return int El número de empleados que usan el rol.
     */
    private function estaRolEnUso($rolId) {
        $count = 0;
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM empleado_rol WHERE rol_id = ?");
            $stmt->bind_param("i", $rolId);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // Manejar el error, quizás loggearlo o devolver -1 para indicar un problema
            error_log("Error al verificar uso de rol: " . $e->getMessage());
            return -1; // Indicar un error
        }
        return $count;
    }

    public function crear($nombre) {
        if (!$this->validarNombre($nombre)) {
            http_response_code(400); // Bad Request
            return "El nombre del rol no es válido o supera los 250 caracteres.";
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO roles (NOMBRE) VALUES (?)");
            $stmt->bind_param("s", $nombre);
            $stmt->execute();
            $stmt->close();
            http_response_code(201); // Created
            return "Rol creado exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al crear rol: " . $e->getMessage()); // Loggear el error
            return "Error al crear el rol: " . $e->getMessage();
        }
    }

    public function editar($id, $nombre) {
        if (!$id || !$this->validarNombre($nombre)) {
            http_response_code(400); // Bad Request
            return "Datos inválidos para edición. ID o nombre no válidos.";
        }

        try {
            $stmt = $this->conn->prepare("UPDATE roles SET NOMBRE=? WHERE ID=?");
            $stmt->bind_param("si", $nombre, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                // Considerar si esto es un 404 o 200 con mensaje si los datos enviados son iguales
                http_response_code(404); // Not Found si no se actualizó ninguna fila
                $stmt->close();
                return "No se encontró el rol con el ID proporcionado o no hubo cambios.";
            }

            $stmt->close();
            http_response_code(200); // OK
            return "Rol actualizado exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al editar rol: " . $e->getMessage()); // Loggear el error
            return "Error al editar el rol: " . $e->getMessage();
        }
    }

    public function eliminar($id) {
        if (!$id) {
            http_response_code(400); // Bad Request
            return "ID inválido para eliminación.";
        }

        // **Nueva Validación de Uso**
        $empleadosUsandoRol = $this->estaRolEnUso($id);

        if ($empleadosUsandoRol > 0) {
            http_response_code(409); // Conflict
            return "No se puede eliminar el rol porque está asignado a " . $empleadosUsandoRol . " empleado(s).";
        } elseif ($empleadosUsandoRol === -1) {
            // Error interno al verificar el uso
            http_response_code(500);
            return "Error interno al verificar si el rol está en uso.";
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM roles WHERE ID=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404); // Not Found si no se eliminó ninguna fila
                $stmt->close();
                return "No se encontró el rol con el ID proporcionado para eliminar.";
            }

            $stmt->close();
            http_response_code(200); // OK
            return "Rol eliminado exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al eliminar rol: " . $e->getMessage()); // Loggear el error
            return "Error al eliminar el rol: " . $e->getMessage();
        }
    }

    public function listar() {
        try {
            $resultado = $this->conn->query("SELECT ID, NOMBRE FROM roles"); // Especificar columnas
            $roles = [];

            while ($fila = $resultado->fetch_assoc()) {
                $roles[] = $fila;
            }
            http_response_code(200); // OK
            return $roles;
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al listar roles: " . $e->getMessage()); // Loggear el error
            return ["error" => "Error al listar roles: " . $e->getMessage()];
        }
    }
}

// Instanciar clase
// Asegúrate de que $conn esté definido desde '../conexion.php'
// O bien, inicializa la conexión aquí si '../conexion.php' solo define una función.
// Ejemplo si tu conexión es solo una función: $conn = connectDB();
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    // Cambiado a JSON para ser consistente con el GET
    echo json_encode(["error" => "Error de conexión a la base de datos."]);
    exit();
}

$rolManager = new RolManager($conn);

// Lógica para POST (crear, editar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    header('Content-Type: text/plain'); // Establecer para mensajes de texto

    switch ($accion) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            echo $rolManager->crear($nombre);
            break;

        case 'editar':
            $id = intval($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            echo $rolManager->editar($id, $nombre);
            break;

        case 'eliminar':
            $id = intval($_POST['id'] ?? 0);
            echo $rolManager->eliminar($id);
            break;

        default:
            http_response_code(400); // Bad Request
            echo "Acción no válida.";
            break;
    }
}

// Lógica para GET (listar)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $roles = $rolManager->listar();
    if (isset($roles['error'])) {
        // Si listar() devolvió un error, ya se estableció el código HTTP 500 dentro de listar()
        echo json_encode($roles); // Devolver el error como JSON
    } else {
        echo json_encode($roles);
    }
}

// Cerrar conexión
if (isset($conn)) {
    $conn->close();
}
?>