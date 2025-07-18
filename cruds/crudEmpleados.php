<?php
include '../conexion.php';

class EmpleadoManager {
    private $conn;

    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    public function crear($data) {
        $validacion = $this->validarDatos($data);
        if ($validacion !== true) return $validacion;

        $stmt = $this->conn->prepare("INSERT INTO empleados (NOMBRE, EMAIL, SEXO, AREA_ID, BOLETIN, DESCRIPCION) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiss", $data['nombre'], $data['email'], $data['sexo'], $data['area_id'], $data['boletin'], $data['descripcion']);

        if ($stmt->execute()) {
            $empleadoId = $this->conn->insert_id;
            $stmt->close();

            $this->asignarRoles($empleadoId, $data['roles']);
            return "Empleado creado exitosamente.";
        } else {
            return "Error al crear: " . $stmt->error;
        }
    }

    public function editar($data) {
        if (!isset($data['id']) || intval($data['id']) <= 0) {
            return "ID invalido para edición.";
        }

        $validacion = $this->validarDatos($data);
        if ($validacion !== true) return $validacion;

        $stmt = $this->conn->prepare("UPDATE empleados SET NOMBRE=?, EMAIL=?, SEXO=?, AREA_ID=?, BOLETIN=?, DESCRIPCION=? WHERE ID=?");
        $stmt->bind_param("sssissi", $data['nombre'], $data['email'], $data['sexo'], $data['area_id'], $data['boletin'], $data['descripcion'], $data['id']);

        if ($stmt->execute()) {
            $stmt->close();

            // Actualizar roles
            $this->eliminarRoles($data['id']);
            $this->asignarRoles($data['id'], $data['roles']);
            return "Empleado actualizado exitosamente.";
        } else {
            return "Error al editar: " . $stmt->error;
        }
    }

    public function eliminar($id) {
        if (!$id) return "ID invalido para eliminación.";

        // Eliminar relaciones en tabla intermedia
        $this->eliminarRoles($id);

        $stmt = $this->conn->prepare("DELETE FROM empleados WHERE ID=?");
        $stmt->bind_param("i", $id);

        return $stmt->execute()
            ? "Empleado eliminado exitosamente."
            : "Error al eliminar: " . $stmt->error;
    }

    public function listar() {
        $query = "SELECT e.ID, e.NOMBRE, e.EMAIL, e.SEXO, a.NOMBRE as AREA, e.BOLETIN, e.DESCRIPCION 
                  FROM empleados e 
                  JOIN areas a ON e.AREA_ID = a.ID";

        $resultado = $this->conn->query($query);
        $empleados = [];

        while ($fila = $resultado->fetch_assoc()) {
            $empleados[] = $fila;
        }

        return $empleados;
    }

    public function obtenerEmpleadoPorId($id) {
        $stmt = $this->conn->prepare(
            "SELECT e.ID, e.NOMBRE, e.EMAIL, e.SEXO, e.AREA_ID, e.BOLETIN, e.DESCRIPCION 
            FROM empleados e 
            WHERE e.ID = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {
            return null;
        }

        $empleado = $resultado->fetch_assoc();
        $empleado['ROLES'] = $this->obtenerRolesEmpleado($id);

        $stmt->close();
        return $empleado;
    }

    private function obtenerRolesEmpleado($empleadoId) {
        $roles = [];
        $stmt = $this->conn->prepare("SELECT ROL_ID FROM empleado_rol WHERE EMPLEADO_ID = ?");
        $stmt->bind_param("i", $empleadoId);
        $stmt->execute();
        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {
            $roles[] = $row['ROL_ID'];
        }

        $stmt->close();
        return $roles;
    }



    private function validarDatos($data) {
        // Validar campos vacíos
        if (empty($data['nombre']) || empty($data['email']) || empty($data['sexo']) || empty($data['area_id']) || empty($data['descripcion']) || empty($data['roles'])) {
            return "Todos los campos son obligatorios.";
        }

        // Nombre: solo letras (con o sin tilde) y espacios, máx 250
        if (!preg_match("/^[a-zA-ZÁÉÍÓÚáéíóúÑñ ]+$/", $data['nombre']) || strlen($data['nombre']) > 250) {
            return "Nombre inválido. Solo se permiten letras y espacios, máximo 250 caracteres.";
        }

        // Email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 250) {
            return "Correo electrónico inválido o demasiado largo.";
        }

        // Sexo
        if (!in_array($data['sexo'], ['M', 'F'])) {
            return "Sexo inválido.";
        }

        // Área
        if (!filter_var($data['area_id'], FILTER_VALIDATE_INT)) {
            return "Área inválida.";
        }

        // Descripción
        if (strlen($data['descripcion']) > 500) {
            return "La descripción no debe superar los 500 caracteres.";
        }

        // Roles
        if (!is_array($data['roles']) || count($data['roles']) === 0) {
            return "Debe seleccionar al menos un rol.";
        }

        return true;
    }

    private function asignarRoles($empleadoId, $roles) {
        $stmt = $this->conn->prepare("INSERT INTO empleado_rol (EMPLEADO_ID, ROL_ID) VALUES (?, ?)");

        foreach ($roles as $rolId) {
            $rolId = intval($rolId);
            $stmt->bind_param("ii", $empleadoId, $rolId);
            $stmt->execute();
        }

        $stmt->close();
    }

    private function eliminarRoles($empleadoId) {
        $stmt = $this->conn->prepare("DELETE FROM empleado_rol WHERE EMPLEADO_ID=?");
        $stmt->bind_param("i", $empleadoId);
        $stmt->execute();
        $stmt->close();
    }
}

// --- Manejo de solicitudes ---
$manager = new EmpleadoManager($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $data = [
        'id'          => $_POST['id'] ?? null,
        'nombre'      => trim($_POST['nombre'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'sexo'        => $_POST['sexo'] ?? '',
        'area_id'     => $_POST['area_id'] ?? '',
        'boletin'     => isset($_POST['boletin']) ? intval($_POST['boletin']) : 0,
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'roles'       => $_POST['roles'] ?? []
    ];

    switch ($accion) {
        case 'crear':
            echo $manager->crear($data);
            break;

        case 'editar':
            echo $manager->editar($data);
            break;

        case 'eliminar':
            echo $manager->eliminar(intval($data['id']));
            break;

        default:
            echo "Acción no válida.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');

    if (isset($_GET['id'])) {
        $empleado = $manager->obtenerEmpleadoPorId(intval($_GET['id']));
        echo json_encode($empleado ?: ["error" => "Empleado no encontrado."]);
    } else {
        echo json_encode($manager->listar());
    }
}

$conn->close();
?>
