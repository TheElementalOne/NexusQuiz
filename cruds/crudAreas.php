<?php
include '../conexion.php';

class AreaController {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    public function procesarSolicitud() {
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
                    echo "Acción no válida.";
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->listar();
        }
    }

    private function crear() {
        $nombre = trim($_POST['nombre'] ?? '');

        if (empty($nombre)) {
            echo "Nombre no puede estar vacío.";
            return;
        }
        if (strlen($nombre) > 250) {
            echo "El nombre no puede exceder 250 caracteres.";
            return;
        }
        $stmt = $this->conn->prepare("INSERT INTO areas (NOMBRE) VALUES (?)");
        $stmt->bind_param("s", $nombre);
        echo $stmt->execute() ? "Área creada exitosamente." : "Error al crear: " . $stmt->error;
        $stmt->close();
    }

    private function editar() {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if (!$id || empty($nombre)) {
            echo "Datos inválidos para edición.";
            return;
        }
        if (strlen($nombre) > 250) {
            echo "El nombre no puede exceder 250 caracteres.";
            return;
        }
        $stmt = $this->conn->prepare("UPDATE areas SET NOMBRE=? WHERE ID=?");
        $stmt->bind_param("si", $nombre, $id);
        echo $stmt->execute() ? "Área actualizada exitosamente." : "Error al editar: " . $stmt->error;
        $stmt->close();
    }


    private function eliminar() {
        $id = intval($_POST['id'] ?? 0);

        if ($id) {
            $stmt = $this->conn->prepare("DELETE FROM areas WHERE ID=?");
            $stmt->bind_param("i", $id);
            echo $stmt->execute() ? "Área eliminada exitosamente." : "Error al eliminar: " . $stmt->error;
            $stmt->close();
        } else {
            echo "ID inválido para eliminación.";
        }
    }

    private function listar() {
        $resultado = $this->conn->query("SELECT * FROM areas");
        $areas = [];

        while ($row = $resultado->fetch_assoc()) {
            $areas[] = $row;
        }

        header('Content-Type: application/json');
        echo json_encode($areas);
    }

    public function __destruct() {
        $this->conn->close();
    }
}

// Instanciación del controlador
$controller = new AreaController($conn);
$controller->procesarSolicitud();
?>
