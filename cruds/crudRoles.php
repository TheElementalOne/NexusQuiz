<?php
include '../conexion.php'; // Asegúrate de que este archivo maneja correctamente la conexión y los errores

class RolManager {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
        // Configurar el modo de reporte de errores de MySQLi
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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
            // Puedes loggear el error para depuración: error_log("Error al crear rol: " . $e->getMessage());
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
                http_response_code(404); // Not Found si no se actualizó ninguna fila
                $stmt->close();
                return "No se encontró el rol con el ID proporcionado o no hubo cambios.";
            }

            $stmt->close();
            http_response_code(200); // OK
            return "Rol actualizado exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            return "Error al editar el rol: " . $e->getMessage();
        }
    }

    public function eliminar($id) {
        if (!$id) {
            http_response_code(400); // Bad Request
            return "ID inválido para eliminación.";
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
            return "Error al eliminar el rol: " . $e->getMessage();
        }
    }

    public function listar() {
        try {
            $resultado = $this->conn->query("SELECT * FROM roles");
            $roles = [];

            while ($fila = $resultado->fetch_assoc()) {
                $roles[] = $fila;
            }
            http_response_code(200); // OK
            return $roles;
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            // En caso de error al listar, es mejor devolver un array vacío o un mensaje de error como JSON
            return ["error" => "Error al listar roles: " . $e->getMessage()];
        }
    }

    private function validarNombre($nombre) {
        return preg_match("/^[a-zA-Z0-9\- ]+$/", $nombre) && strlen($nombre) > 0 && strlen($nombre) <= 250;
    }
}

// Instanciar clase
// Asegúrate de que $conn esté definido desde '../conexion.php'
if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión a la base de datos."]);
    exit();
}

$rolManager = new RolManager($conn);

// Lógica para POST (crear, editar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

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
    echo json_encode($rolManager->listar());
}

// Cerrar conexión
if (isset($conn)) {
    $conn->close();
}
?>