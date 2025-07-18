<?php
// Incluye el archivo de conexión que proporciona la variable $conn (instancia de mysqli)
include '../conexion.php';

/**
 * Clase AreaController
 * Controlador para gestionar operaciones CRUD sobre la tabla "areas".
 */
class AreaController {
    /**
     * @var mysqli Conexión a la base de datos
     */
    private $conn;

    /**
     * Constructor del controlador.
     *
     * @param mysqli $conexion Instancia de conexión MySQLi
     */
    public function __construct($conexion) {
        $this->conn = $conexion;
        // Configura MySQLi para lanzar excepciones en caso de error
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    /**
     * Procesa la solicitud HTTP (GET o POST) y ejecuta la acción correspondiente.
     */
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
                    http_response_code(400); // Solicitud inválida
                    echo "Acción no válida.";
                    break;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->listar(); // Listar todas las áreas
        }
    }

    /**
     * Valida el nombre de un área según las reglas de negocio.
     *
     * @param string $nombre Nombre a validar
     * @return bool True si es válido, False en caso contrario (ya emite errores)
     */
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

    /**
     * Verifica si un área está siendo utilizada por algún empleado.
     *
     * @param int $areaId ID del área a verificar
     * @return int Número de empleados que usan el área, -1 si ocurre un error
     */
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
            return -1;
        }

        return $count;
    }

    /**
     * Crea una nueva área en la base de datos.
     * Requiere el campo POST 'nombre'.
     */
    private function crear() {
        $nombre = trim($_POST['nombre'] ?? '');

        if (!$this->validarNombre($nombre)) {
            return;
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
            http_response_code(500);
            error_log("Error al crear área: " . $e->getMessage());
            echo "Error al crear el área: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al crear área: " . $e->getMessage());
            echo "Error inesperado al crear el área.";
        }
    }

    /**
     * Edita un área existente.
     * Requiere los campos POST 'id' y 'nombre'.
     */
    private function editar() {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');

        if (!$id) {
            http_response_code(400);
            echo "ID no válido para edición.";
            return;
        }

        if (!$this->validarNombre($nombre)) {
            return;
        }

        try {
            $stmt = $this->conn->prepare("UPDATE areas SET NOMBRE=? WHERE ID=?");
            if ($stmt === false) {
                throw new Exception("Error en la preparación de la consulta: " . $this->conn->error);
            }

            $stmt->bind_param("si", $nombre, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404); // No encontrado o sin cambios
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

    /**
     * Elimina un área si no está siendo usada por empleados.
     * Requiere el campo POST 'id'.
     */
    private function eliminar() {
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo "ID inválido para eliminación.";
            return;
        }

        // Validar si el área está en uso
        $empleadosUsandoArea = $this->estaAreaEnUso($id);

        if ($empleadosUsandoArea > 0) {
            http_response_code(409); // Conflicto
            echo "No se puede eliminar el área porque está asignada a " . $empleadosUsandoArea . " empleado(s).";
            return;
        } elseif ($empleadosUsandoArea === -1) {
            http_response_code(500); // Error interno
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
                http_response_code(404); // No encontrado
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

    /**
     * Lista todas las áreas registradas en formato JSON.
     */
    private function listar() {
        header('Content-Type: application/json');

        try {
            $resultado = $this->conn->query("SELECT ID, NOMBRE FROM areas");
            if ($resultado === false) {
                throw new Exception("Error al ejecutar la consulta de listar: " . $this->conn->error);
            }

            $areas = [];

            while ($row = $resultado->fetch_assoc()) {
                $areas[] = $row;
            }

            http_response_code(200);
            echo json_encode($areas);
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            error_log("Error al listar áreas: " . $e->getMessage());
            echo json_encode(["error" => "Error al listar áreas: " . $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al listar áreas: " . $e->getMessage());
            echo json_encode(["error" => "Error inesperado al listar áreas: " . $e->getMessage()]);
        }
    }

    /**
     * Destructor: Cierra la conexión a la base de datos.
     */
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Validación de la conexión antes de crear el controlador
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Error crítico: No se pudo establecer conexión con la base de datos.";
    exit();
}

// Instancia del controlador y procesamiento de la solicitud
$controller = new AreaController($conn);
$controller->procesarSolicitud();
?>
