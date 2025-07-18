<?php
include '../conexion.php';

/**
 * Clase RolManager
 *
 * Esta clase gestiona las operaciones CRUD (Crear, Leer, Actualizar, Eliminar)
 * para la tabla 'roles' en la base de datos. Se conecta a la base de datos
 * usando una instancia de mysqli y maneja las validaciones de los datos de entrada
 * y las respuestas HTTP.
 */
class RolManager {
    private $conn;

    /**
     * Constructor de la clase RolManager.
     *
     * Inicializa la conexion a la base de datos y configura el reporte de errores de mysqli.
     *
     * @param mysqli $conexion Una instancia de la conexion a la base de datos mysqli.
     */
    public function __construct($conexion) {
        $this->conn = $conexion;
        // Configurar el modo de reporte de errores de MySQLi
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    /**
     * Valida el nombre de un rol.
     *
     * Verifica que el nombre contenga solo caracteres alfanumericos, guiones o espacios,
     * que no este vacio y que su longitud no exceda los 250 caracteres.
     *
     * @param string $nombre El nombre del rol a validar.
     * @return bool Retorna true si el nombre es valido, false en caso contrario.
     */
    private function validarNombre($nombre) {
        return preg_match("/^[a-zA-Z0-9\- ]+$/", $nombre) && strlen($nombre) > 0 && strlen($nombre) <= 250;
    }

    /**
     * Verifica si un rol esta siendo utilizado por algun empleado.
     *
     * Asume una tabla pivote 'empleado_rol' que relaciona empleados con roles.
     *
     * @param int $rolId El ID del rol a verificar.
     * @return int El numero de empleados que usan el rol. Retorna -1 si ocurre un error en la consulta.
     */
    private function estaRolEnUso($rolId) {
        $count = 0;
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM empleado_rol WHERE rol_id = ?");
            if ($stmt === false) {
                // Manejar error en la preparacion de la consulta
                throw new Exception("Error en la preparacion de la consulta 'estaRolEnUso': " . $this->conn->error);
            }
            $stmt->bind_param("i", $rolId);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            // Manejar el error, quizas loggearlo o devolver -1 para indicar un problema
            error_log("Error al verificar uso de rol: " . $e->getMessage());
            return -1; // Indicar un error
        } catch (Exception $e) {
            error_log("Error general en estaRolEnUso: " . $e->getMessage());
            return -1; // Indicar un error
        }
        return $count;
    }

    /**
     * Crea un nuevo rol en la base de datos.
     *
     * Valida el nombre del rol antes de la insercion.
     * Establece el codigo de respuesta HTTP segun el resultado de la operacion.
     *
     * @param string $nombre El nombre del rol a crear.
     * @return string Retorna un mensaje de exito o un mensaje de error.
     */
    public function crear($nombre) {
        if (!$this->validarNombre($nombre)) {
            http_response_code(400); // Bad Request
            return "El nombre del rol no es valido o supera los 250 caracteres.";
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO roles (NOMBRE) VALUES (?)");
            if ($stmt === false) {
                throw new Exception("Error en la preparacion de la consulta 'crear': " . $this->conn->error);
            }
            $stmt->bind_param("s", $nombre);
            $stmt->execute();
            $stmt->close();
            http_response_code(201); // Created
            return "Rol creado exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al crear rol: " . $e->getMessage()); // Loggear el error
            return "Error al crear el rol: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al crear rol: " . $e->getMessage());
            return "Error inesperado al crear el rol.";
        }
    }

    /**
     * Edita un rol existente en la base de datos.
     *
     * Valida el ID y el nombre del rol.
     * Establece el codigo de respuesta HTTP segun el resultado de la operacion.
     *
     * @param int $id El ID del rol a editar.
     * @param string $nombre El nuevo nombre del rol.
     * @return string Retorna un mensaje de exito o un mensaje de error.
     */
    public function editar($id, $nombre) {
        if (!$id || !$this->validarNombre($nombre)) {
            http_response_code(400); // Bad Request
            return "Datos invalidos para edicion. ID o nombre no validos.";
        }

        try {
            $stmt = $this->conn->prepare("UPDATE roles SET NOMBRE=? WHERE ID=?");
            if ($stmt === false) {
                throw new Exception("Error en la preparacion de la consulta 'editar': " . $this->conn->error);
            }
            $stmt->bind_param("si", $nombre, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                // Considerar si esto es un 404 o 200 con mensaje si los datos enviados son iguales
                http_response_code(404); // Not Found si no se actualizo ninguna fila
                $stmt->close();
                return "No se encontro el rol con el ID proporcionado o no hubo cambios.";
            }

            $stmt->close();
            http_response_code(200); // OK
            return "Rol actualizado exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al editar rol: " . $e->getMessage()); // Loggear el error
            return "Error al editar el rol: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al editar rol: " . $e->getMessage());
            return "Error inesperado al editar el rol.";
        }
    }

    /**
     * Elimina un rol de la base de datos.
     *
     * Valida el ID del rol y verifica si el rol esta en uso por algun empleado
     * antes de intentar eliminarlo.
     * Establece el codigo de respuesta HTTP segun el resultado de la operacion.
     *
     * @param int $id El ID del rol a eliminar.
     * @return string Retorna un mensaje de exito o un mensaje de error.
     */
    public function eliminar($id) {
        if (!$id) {
            http_response_code(400); // Bad Request
            return "ID invalido para eliminacion.";
        }

        // Nueva Validacion de Uso
        $empleadosUsandoRol = $this->estaRolEnUso($id);

        if ($empleadosUsandoRol > 0) {
            http_response_code(409); // Conflict
            return "No se puede eliminar el rol porque esta asignado a " . $empleadosUsandoRol . " empleado(s).";
        } elseif ($empleadosUsandoRol === -1) {
            // Error interno al verificar el uso
            http_response_code(500);
            return "Error interno al verificar si el rol esta en uso.";
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM roles WHERE ID=?");
            if ($stmt === false) {
                throw new Exception("Error en la preparacion de la consulta 'eliminar': " . $this->conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404); // Not Found si no se elimino ninguna fila
                $stmt->close();
                return "No se encontro el rol con el ID proporcionado para eliminar.";
            }

            $stmt->close();
            http_response_code(200); // OK
            return "Rol eliminado exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al eliminar rol: " . $e->getMessage()); // Loggear el error
            return "Error al eliminar el rol: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al eliminar rol: " . $e->getMessage());
            return "Error inesperado al eliminar el rol.";
        }
    }

    /**
     * Lista todos los roles disponibles en la base de datos.
     *
     * No espera parametros.
     * Establece el codigo de respuesta HTTP segun el resultado de la operacion.
     *
     * @return array Retorna un array de arrays asociativos, donde cada sub-array
     * representa un rol con su ID y NOMBRE. Si ocurre un error, retorna un array con una clave 'error'.
     */
    public function listar() {
        try {
            $resultado = $this->conn->query("SELECT ID, NOMBRE FROM roles"); // Especificar columnas
            if ($resultado === false) {
                throw new Exception("Error al ejecutar la consulta 'listar': " . $this->conn->error);
            }
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
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al listar roles: " . $e->getMessage());
            return ["error" => "Error inesperado al listar roles: " . $e->getMessage()];
        }
    }
}

/*

## Manejo de Solicitudes HTTP

Este bloque de codigo se encarga de la logica principal para procesar las solicitudes HTTP (GET y POST) y 
utiliza la clase `RolManager` para interactuar con la base de datos.

### Inicializacion y Comprobacion de Conexion 🚦

Antes de cualquier operacion, se verifica que la conexion a la base de datos (`$conn`) haya sido establecida 
por el archivo `conexion.php`. Si la conexion falla, se establece un **codigo de respuesta HTTP 500** (`Internal Server Error`) y 
se envia un **JSON de error** al cliente antes de terminar la ejecucion del script. Esto asegura una respuesta consistente en formato JSON.

### Solicitudes POST

Si la solicitud es de tipo **POST**, se espera un parametro `accion` en `$_POST` para determinar la operacion a realizar.
 El `Content-Type` de la respuesta se establece inicialmente a `text/plain` para los mensajes de exito o error textuales que 
 devuelven las funciones `crear`, `editar` y `eliminar`.

-   **`crear`**:
    -   **Datos esperados**: `$_POST['nombre']` (string), que es el nombre del nuevo rol.
    -   **Funcion llamada**: `$rolManager->crear($nombre)`.
    -   **Proposito**: Añadir un nuevo rol a la tabla `roles`.

-   **`editar`**:
    -   **Datos esperados**: `$_POST['id']` (int), el ID del rol a modificar, y `$_POST['nombre']` (string), el nuevo nombre del rol.
    -   **Funcion llamada**: `$rolManager->editar($id, $nombre)`.
    -   **Proposito**: Actualizar el nombre de un rol existente.

-   **`eliminar`**:
    -   **Datos esperados**: `$_POST['id']` (int), el ID del rol a eliminar.
    -   **Funcion llamada**: `$rolManager->eliminar($id)`.
    -   **Proposito**: Remover un rol de la tabla `roles`, siempre y cuando no este en uso por ningun empleado. Se verifica la tabla intermedia `empleado_rol` para esto.

-   **`default`**:
    -   Si la `accion` no es reconocida, se envia un **codigo de respuesta HTTP 400** (`Bad Request`) y un mensaje indicando que la accion no es valida.

### Solicitudes GET

Si la solicitud es de tipo **GET**, el encabezado `Content-Type` se establece a `application/json` para asegurar que la respuesta sea en formato JSON.

-   **Datos esperados**: Ninguno en particular para la operacion de listado general de roles.
-   **Funcion llamada**: `$rolManager->listar()`.
-   **Proposito**: Obtener una lista de todos los roles almacenados en la base de datos.
-   **Respuesta**: Se devuelve un **array JSON** de objetos de rol. Si la funcion `listar()` retorna un error (como un array con la clave 'error'), ese error se codifica directamente como JSON en la respuesta, manteniendo el codigo HTTP que ya se habia establecido internamente en `listar()` (normalmente 500 para errores).

### Cierre de Conexion 

Al finalizar la ejecucion del script, se verifica si la variable `$conn` esta definida y, si lo esta, se cierra la conexion a la base de datos (`$conn->close()`) para liberar los recursos.

*/