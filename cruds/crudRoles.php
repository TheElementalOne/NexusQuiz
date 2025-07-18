<?php
include '../conexion.php';

class RolManager {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    public function crear($nombre) {
        if (!$this->validarNombre($nombre)) {
            return "El nombre del rol no es válido o supera los 250 caracteres.";
        }

        $stmt = $this->conn->prepare("INSERT INTO roles (NOMBRE) VALUES (?)");
        $stmt->bind_param("s", $nombre);

        $resultado = $stmt->execute() ? "Rol creado exitosamente." : "Error al crear: " . $stmt->error;
        $stmt->close();

        return $resultado;
    }

    public function editar($id, $nombre) {
        if (!$id || !$this->validarNombre($nombre)) {
            return "Datos inválidos para edición.";
        }

        $stmt = $this->conn->prepare("UPDATE roles SET NOMBRE=? WHERE ID=?");
        $stmt->bind_param("si", $nombre, $id);

        $resultado = $stmt->execute() ? "Rol actualizado exitosamente." : "Error al editar: " . $stmt->error;
        $stmt->close();

        return $resultado;
    }

    public function eliminar($id) {
        if (!$id) {
            return "ID inválido para eliminación.";
        }

        $stmt = $this->conn->prepare("DELETE FROM roles WHERE ID=?");
        $stmt->bind_param("i", $id);

        $resultado = $stmt->execute() ? "Rol eliminado exitosamente." : "Error al eliminar: " . $stmt->error;
        $stmt->close();

        return $resultado;
    }

    public function listar() {
        $resultado = $this->conn->query("SELECT * FROM roles");
        $roles = [];

        while ($fila = $resultado->fetch_assoc()) {
            $roles[] = $fila;
        }

        return $roles;
    }

    private function validarNombre($nombre) {
        return preg_match("/^[a-zA-Z0-9\- ]+$/", $nombre) && strlen($nombre) > 0 && strlen($nombre) <= 250;
    }
}

// Instanciar clase
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
$conn->close();
?>
