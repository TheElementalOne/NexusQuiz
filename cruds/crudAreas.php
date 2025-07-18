<?php
include '../conexion.php'; // Asume que $conn es una instancia de mysqli

class AreaController {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    public function procesarSolicitud() {
        header('Content-Type: text/plain');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';
            switch ($accion) {
                case 'crear':
                    $this->crear();
                    break;
                case 'editar':
                    $this->editar();
                    break;
                case 'eliminar':
                    $this->eliminar();
                    break;
                default:
                    http_response_code(400); // Bad Request
                    echo "Acción no válida.";
                    break;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->listar();
        }
    }

    // Validación del nombre (centralizada)
    private function validarNombre($nombre) {
        if (empty($nombre)) {
            http_response_code(400);
            echo "El nombre no puede estar vacío.";
            return false;
        }
        if (strlen($nombre) > 250) {
            http_response_code(400);
            echo "El nombre no puede exceder 250 caracteres.";
            return false;
        }
        if (!preg_match("/^[a-zA-Z0-9\- ]+$/", $nombre)) {
            http_response_code(400);
            echo "Solo se permiten letras, números, guiones y espacios en el nombre.";
            return false;
        }
        return true;
    }

    private function estaAreaEnUso($areaId) {
        $count = 0;
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM empleados WHERE area_id = ?");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("i", $areaId);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error al verificar uso de área: " . $e->getMessage());
            return -1; // Indicar un error
        }
        return $count;
    }

    private function crear() {
        $nombre = trim($_POST['nombre'] ?? '');

        if (!$this->validarNombre($nombre)) {
            return; // validarNombre ya maneja el http_response_code y echo
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO areas (NOMBRE) VALUES (?)");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("s", $nombre);
            $stmt->execute();
            $stmt->close();
            http_response_code(201); // Created
            echo "Área creada exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al crear área: " . $e->getMessage());
            echo "Error al crear el área: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al crear área: " . $e->getMessage());
            echo "Error inesperado al crear el área.";
        }
    }

    private function editar() {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');

        if (!$id) {
            http_response_code(400);
            echo "ID no válido para edición.";
            return;
        }
        if (!$this->validarNombre($nombre)) {
            return; // validarNombre ya maneja el http_response_code y echo
        }

        try {
            $stmt = $this->conn->prepare("UPDATE areas SET NOMBRE=? WHERE ID=?");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("si", $nombre, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404);
                echo "No se encontró el área con el ID proporcionado o no hubo cambios.";
            } else {
                http_response_code(200); // OK
                echo "Área actualizada exitosamente.";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            error_log("Error al editar área: " . $e->getMessage());
            echo "Error al editar el área: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al editar área: " . $e->getMessage());
            echo "Error inesperado al editar el área.";
        }
    }

    private function eliminar() {
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            http_response_code(400); // Bad Request
            echo "ID inválido para eliminación.";
            return;
        }

        // **Validación de Uso**
        $empleadosUsandoArea = $this->estaAreaEnUso($id);

        if ($empleadosUsandoArea > 0) {
            http_response_code(409); // Conflict
            echo "No se puede eliminar el área porque está asignada a " . $empleadosUsandoArea . " empleado(s).";
            return;
        } elseif ($empleadosUsandoArea === -1) {
            // Error interno al verificar el uso
            http_response_code(500);
            echo "Error interno al verificar si el área está en uso.";
            return;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM areas WHERE ID=?");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404); // Not Found
                echo "No se encontró el área con el ID proporcionado para eliminar.";
            } else {
                http_response_code(200); // OK
                echo "Área eliminada exitosamente.";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            error_log("Error al eliminar área: " . $e->getMessage());
            echo "Error al eliminar el área: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al eliminar área: " . $e->getMessage());
            echo "Error inesperado al eliminar el área.";
        }
    }

    private function listar() {
        header('Content-Type: application/json'); // Set Content-Type for JSON output

        try {
            $resultado = $this->conn->query("SELECT ID, NOMBRE FROM areas");
            if ($resultado === false) {
                throw new Exception("Error al ejecutar la consulta de listar: " . $this->conn->error);
            }
            $areas = [];

            while ($row = $resultado->fetch_assoc()) {
                $areas[] = $row;
            }
            http_response_code(200); // OK
            echo json_encode($areas);
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al listar áreas: " . $e->getMessage());
            echo json_encode(["error" => "Error al listar áreas: " . $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al listar áreas: " . $e->getMessage());
            echo json_encode(["error" => "Error inesperado al listar áreas: " . $e->getMessage()]);
        }
    }

    public function __destruct() {
        if ($this->conn) { // Asegurarse de que la conexión exista antes de intentar cerrarla
            $this->conn->close();
        }
    }
}

if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    header('Content-Type: text/plain'); // Por si acaso no se ha establecido todavía
    echo "Error crítico: No se pudo establecer conexión con la base de datos.";
    exit();
}

$controller = new AreaController($conn);
$controller->procesarSolicitud();
?>