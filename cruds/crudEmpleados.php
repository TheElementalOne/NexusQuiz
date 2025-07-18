<?php
include '../conexion.php';

/**
 * Clase EmpleadoManager
 *
 * Esta clase se encarga de gestionar las operaciones CRUD (Crear, Leer, Actualizar, Eliminar)
 * de los empleados en la base de datos. Ademas, maneja la asignacion y eliminacion de roles
 * para cada empleado y realiza validaciones de los datos de entrada.
 */
class EmpleadoManager {
    private $conn;

    /**
     * Constructor de la clase EmpleadoManager.
     *
     * @param mysqli $conexion Una instancia de la conexion a la base de datos mysqli.
     */
    public function __construct($conexion) {
        $this->conn = $conexion;
    }

    /**
     * Crea un nuevo empleado en la base de datos.
     *
     * Valida los datos recibidos y, si son validos, inserta el nuevo empleado
     * y asigna los roles correspondientes.
     *
     * @param array $data Un array asociativo con los datos del empleado.
     * Debe contener:
     * - 'nombre' (string): Nombre completo del empleado.
     * - 'email' (string): Correo electronico del empleado.
     * - 'sexo' (string): Sexo del empleado ('M' o 'F').
     * - 'area_id' (int): ID del area a la que pertenece el empleado.
     * - 'boletin' (int): Indicador de suscripcion a boletin (1 si si, 0 si no).
     * - 'descripcion' (string): Descripcion del perfil del empleado.
     * - 'roles' (array): Un array de IDs de los roles asociados al empleado.
     * @return string Retorna un mensaje de exito o un mensaje de error si la operacion falla.
     */
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

    /**
     * Edita un empleado existente en la base de datos.
     *
     * Valida el ID del empleado y los demas datos. Si son validos, actualiza la informacion
     * del empleado y sus roles asociados.
     *
     * @param array $data Un array asociativo con los datos del empleado a editar.
     * Debe contener:
     * - 'id' (int): ID del empleado a editar.
     * - 'nombre' (string): Nuevo nombre completo del empleado.
     * - 'email' (string): Nuevo correo electronico del empleado.
     * - 'sexo' (string): Nuevo sexo del empleado ('M' o 'F').
     * - 'area_id' (int): Nuevo ID del area a la que pertenece el empleado.
     * - 'boletin' (int): Nuevo indicador de suscripcion a boletin (1 si si, 0 si no).
     * - 'descripcion' (string): Nueva descripcion del perfil del empleado.
     * - 'roles' (array): Un array de IDs de los nuevos roles asociados al empleado.
     * @return string Retorna un mensaje de exito o un mensaje de error si la operacion falla.
     */
    public function editar($data) {
        if (!isset($data['id']) || intval($data['id']) <= 0) {
            return "ID invalido para edicion.";
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

    /**
     * Elimina un empleado de la base de datos.
     *
     * Primero elimina las relaciones de roles del empleado y luego el empleado en si.
     *
     * @param int $id El ID del empleado a eliminar.
     * @return string Retorna un mensaje de exito o un mensaje de error si la operacion falla.
     */
    public function eliminar($id) {
        if (!$id) return "ID invalido para eliminacion.";

        // Eliminar relaciones en tabla intermedia
        $this->eliminarRoles($id);

        $stmt = $this->conn->prepare("DELETE FROM empleados WHERE ID=?");
        $stmt->bind_param("i", $id);

        return $stmt->execute()
            ? "Empleado eliminado exitosamente."
            : "Error al eliminar: " . $stmt->error;
    }

    /**
     * Lista todos los empleados con su informacion de area asociada.
     *
     * @return array Retorna un array de arrays asociativos, donde cada sub-array
     * representa un empleado con sus campos ID, NOMBRE, EMAIL, SEXO,
     * AREA (nombre del area), BOLETIN y DESCRIPCION.
     */
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

    /**
     * Obtiene la informacion detallada de un empleado por su ID.
     *
     * Incluye los roles asociados al empleado.
     *
     * @param int $id El ID del empleado a buscar.
     * @return array|null Retorna un array asociativo con la informacion del empleado
     * y un array de IDs de roles bajo la clave 'ROLES', o null si el empleado no se encuentra.
     */
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

    /**
     * Obtiene los IDs de los roles asignados a un empleado especifico.
     *
     * @param int $empleadoId El ID del empleado.
     * @return array Retorna un array de IDs de roles.
     */
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

    /**
     * Valida los datos de un empleado.
     *
     * Verifica que los campos obligatorios no esten vacios y que los datos cumplan
     * con los formatos y longitudes esperados (nombre, email, sexo, area, descripcion, roles).
     *
     * @param array $data Un array asociativo con los datos del empleado a validar.
     * @return bool|string Retorna true si todos los datos son validos, o un string con el mensaje de error.
     */
    private function validarDatos($data) {
        // Validar campos vacios
        if (empty($data['nombre']) || empty($data['email']) || empty($data['sexo']) || empty($data['area_id']) || empty($data['descripcion']) || empty($data['roles'])) {
            return "Todos los campos son obligatorios.";
        }

        // Nombre: solo letras (con o sin tilde) y espacios, max 250
        if (!preg_match("/^[a-zA-Z ]+$/", $data['nombre']) || strlen($data['nombre']) > 250) {
            return "Nombre invalido. Solo se permiten letras y espacios, maximo 250 caracteres.";
        }

        // Email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 250) {
            return "Correo electronico invalido o demasiado largo.";
        }

        // Sexo
        if (!in_array($data['sexo'], ['M', 'F'])) {
            return "Sexo invalido.";
        }

        // Area
        if (!filter_var($data['area_id'], FILTER_VALIDATE_INT)) {
            return "Area invalida.";
        }

        // Descripcion
        if (strlen($data['descripcion']) > 500) {
            return "La descripcion no debe superar los 500 caracteres.";
        }

        // Roles
        if (!is_array($data['roles']) || count($data['roles']) === 0) {
            return "Debe seleccionar al menos un rol.";
        }

        return true;
    }

    /**
     * Asigna roles a un empleado.
     *
     * Inserta los IDs de los roles en la tabla intermedia 'empleado_rol'
     * para el empleado especificado.
     *
     * @param int $empleadoId El ID del empleado al que se le asignaran los roles.
     * @param array $roles Un array de IDs de roles a asignar.
     */
    private function asignarRoles($empleadoId, $roles) {
        $stmt = $this->conn->prepare("INSERT INTO empleado_rol (EMPLEADO_ID, ROL_ID) VALUES (?, ?)");

        foreach ($roles as $rolId) {
            $rolId = intval($rolId);
            $stmt->bind_param("ii", $empleadoId, $rolId);
            $stmt->execute();
        }

        $stmt->close();
    }

    /**
     * Elimina todos los roles asignados a un empleado.
     *
     * Elimina las entradas correspondientes en la tabla 'empleado_rol'
     * para el empleado especificado.
     *
     * @param int $empleadoId El ID del empleado del que se eliminaran los roles.
     */
    private function eliminarRoles($empleadoId) {
        $stmt = $this->conn->prepare("DELETE FROM empleado_rol WHERE EMPLEADO_ID=?");
        $stmt->bind_param("i", $empleadoId);
        $stmt->execute();
        $stmt->close();
    }
}

/*
## Manejo de Solicitudes HTTP

Este bloque de codigo se encarga de la logica principal para procesar las solicitudes HTTP (GET y POST) y utiliza la clase `EmpleadoManager` para interactuar con la base de datos.

### Inicializacion y Comprobacion de Conexion

Antes de cualquier operacion, se crea una instancia de `EmpleadoManager` utilizando la conexion a la base de datos `$conn`, que se asume que viene del archivo `conexion.php`. Se debe asegurar que la conexion se haya establecido correctamente.

### Solicitudes POST

Si la solicitud es de tipo **POST**, se espera un parametro `accion` para determinar la operacion a realizar:

-   **`crear`**:
    -   **Datos esperados**: Un array `$_POST` con las claves: `nombre`, `email`, `sexo`, `area_id`, `boletin` (opcional, 0 por defecto si no esta presente), `descripcion`, y `roles` (un array de IDs de roles).
    -   **Funcion llamada**: `$manager->crear($data)`.
    -   **Proposito**: Registrar un nuevo empleado con sus datos y roles.

-   **`editar`**:
    -   **Datos esperados**: Un array `$_POST` con las claves: `id` (ID del empleado a editar), `nombre`, `email`, `sexo`, `area_id`, `boletin` (opcional, 0 por defecto), `descripcion`, y `roles` (un array de IDs de roles).
    -   **Funcion llamada**: `$manager->editar($data)`.
    -   **Proposito**: Actualizar la informacion de un empleado existente y sus roles.

-   **`eliminar`**:
    -   **Datos esperados**: Un array `$_POST` con la clave `id` (ID del empleado a eliminar).
    -   **Funcion llamada**: `$manager->eliminar(intval($data['id']))`.
    -   **Proposito**: Eliminar un empleado de la base de datos, incluyendo sus roles asociados.

-   **`default`**:
    -   Si la `accion` no es reconocida, se envia un mensaje de "Accion no valida.".

### Solicitudes GET

Si la solicitud es de tipo **GET**, el encabezado `Content-Type` se establece a `application/json` para asegurar que la respuesta sea en formato JSON.

-   **Obtener Empleado por ID**:
    -   **Datos esperados**: Si se recibe un parametro `id` en `$_GET`.
    -   **Funcion llamada**: `$manager->obtenerEmpleadoPorId(intval($_GET['id']))`.
    -   **Proposito**: Recuperar la informacion de un empleado especifico, incluyendo sus roles.
    -   **Respuesta**: Un objeto JSON del empleado o un objeto de error si no se encuentra.

-   **Listar Todos los Empleados**:
    -   **Datos esperados**: Si no se recibe el parametro `id`.
    -   **Funcion llamada**: `$manager->listar()`.
    -   **Proposito**: Obtener una lista de todos los empleados.
    -   **Respuesta**: Un array JSON de objetos de empleado.

### Cierre de Conexion

Finalmente, la conexion a la base de datos `$conn` se cierra con `$conn->close()` para liberar los recursos.
*/