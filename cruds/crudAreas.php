<?php
include '../conexion.php';

/**
 * Clase AreaController
 *
 * Esta clase maneja las operaciones CRUD (Crear, Leer, Actualizar, Eliminar) para la tabla 'areas'
 * en la base de datos. Se conecta a la base de datos usando una instancia de mysqli
 * y responde a las solicitudes HTTP (POST para crear, editar, eliminar; GET para listar).
 */
class AreaController {
    private $conn;

    /**
     * Constructor de la clase AreaController.
     *
     * Inicializa la conexion a la base de datos y configura el reporte de errores de mysqli.
     *
     * @param mysqli $conexion Una instancia de la conexion a la base de datos mysqli.
     */
    public function __construct($conexion) {
        $this->conn = $conexion;
        // Configura mysqli para reportar errores y excepciones.
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    /**
     * Procesa la solicitud HTTP entrante.
     *
     * Determina el tipo de solicitud (POST o GET) y la accion a realizar
     * (crear, editar, eliminar o listar) basandose en los parametros recibidos.
     * Establece el encabezado 'Content-Type' adecuado para la respuesta.
     *
     * Datos esperados:
     * - Para solicitudes POST:
     * - 'accion' (string): La accion a realizar ('crear', 'editar', 'eliminar').
     * - Si 'accion' es 'crear': espera 'nombre' (string) en $_POST.
     * - Si 'accion' es 'editar': espera 'id' (int) y 'nombre' (string) en $_POST.
     * - Si 'accion' es 'eliminar': espera 'id' (int) en $_POST.
     * - Para solicitudes GET: No espera parametros especificos, lista todas las areas.
     */
    public function procesarSolicitud() {
        header('Content-Type: text/plain'); // Por defecto, se puede cambiar segun la accion

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
                    echo "Accion no valida.";
                    break;
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->listar();
        }
    }

    /**
     * Valida el nombre de un area.
     *
     * Verifica que el nombre no este vacio, no exceda los 250 caracteres
     * y solo contenga letras, numeros, guiones y espacios.
     * En caso de error de validacion, establece el codigo de respuesta HTTP a 400
     * y envia un mensaje de error.
     *
     * @param string $nombre El nombre del area a validar.
     * @return bool Retorna true si el nombre es valido, false en caso contrario.
     */
    private function validarNombre($nombre) {
        if (empty($nombre)) {
            http_response_code(400);
            echo "El nombre no puede estar vacio.";
            return false;
        }
        if (strlen($nombre) > 250) {
            http_response_code(400);
            echo "El nombre no puede exceder 250 caracteres.";
            return false;
        }
        if (!preg_match("/^[a-zA-Z0-9\- ]+$/", $nombre)) {
            http_response_code(400);
            echo "Solo se permiten letras, numeros, guiones y espacios en el nombre.";
            return false;
        }
        return true;
    }

    /**
     * Verifica si un area esta siendo utilizada por algun empleado.
     *
     * Consulta la tabla 'empleados' para contar cuantos empleados tienen asignada
     * el 'area_id' proporcionado.
     *
     * @param int $areaId El ID del area a verificar.
     * @return int Retorna el numero de empleados que usan el area. Retorna -1 si ocurre un error en la consulta.
     */
    private function estaAreaEnUso($areaId) {
        $count = 0;
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM empleados WHERE area_id = ?");
            if ($stmt === false) {
                throw new Exception("Error en la preparacion de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("i", $areaId);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error al verificar uso de area: " . $e->getMessage());
            return -1; // Indicar un error
        }
        return $count;
    }

    /**
     * Crea una nueva area en la base de datos.
     *
     * Espera el parametro 'nombre' en la solicitud POST.
     * Valida el nombre antes de intentar insertar en la base de datos.
     * Responde con un codigo HTTP 201 (Created) si es exitoso o 500 (Internal Server Error)
     * en caso de fallo.
     *
     * Datos esperados en $_POST:
     * - 'nombre' (string): El nombre de la nueva area.
     */
    private function crear() {
        $nombre = trim($_POST['nombre'] ?? '');

        if (!$this->validarNombre($nombre)) {
            return; // validarNombre ya maneja el http_response_code y echo
        }

        try {
            $stmt = $this->conn->prepare("INSERT INTO areas (NOMBRE) VALUES (?)");
            if ($stmt === false) {
                throw new Exception("Error en la preparacion de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("s", $nombre);
            $stmt->execute();
            $stmt->close();
            http_response_code(201); // Created
            echo "Area creada exitosamente.";
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            error_log("Error al crear area: " . $e->getMessage());
            echo "Error al crear el area: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al crear area: " . $e->getMessage());
            echo "Error inesperado al crear el area.";
        }
    }

    /**
     * Edita un area existente en la base de datos.
     *
     * Espera los parametros 'id' y 'nombre' en la solicitud POST.
     * Valida el ID y el nombre antes de intentar actualizar.
     * Responde con un codigo HTTP 200 (OK) si la actualizacion fue exitosa,
     * 404 (Not Found) si el ID no existe o no hubo cambios, o 500 en caso de error.
     *
     * Datos esperados en $_POST:
     * - 'id' (int): El ID del area a editar.
     * - 'nombre' (string): El nuevo nombre del area.
     */
    private function editar() {
        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');

        if (!$id) {
            http_response_code(400);
            echo "ID no valido para edicion.";
            return;
        }
        if (!$this->validarNombre($nombre)) {
            return; // validarNombre ya maneja el http_response_code y echo
        }

        try {
            $stmt = $this->conn->prepare("UPDATE areas SET NOMBRE=? WHERE ID=?");
            if ($stmt === false) {
                throw new Exception("Error en la preparacion de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("si", $nombre, $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404);
                echo "No se encontro el area con el ID proporcionado o no hubo cambios.";
            } else {
                http_response_code(200); // OK
                echo "Area actualizada exitosamente.";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            error_log("Error al editar area: " . $e->getMessage());
            echo "Error al editar el area: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al editar area: " . $e->getMessage());
            echo "Error inesperado al editar el area.";
        }
    }

    /**
     * Elimina un area de la base de datos.
     *
     * Espera el parametro 'id' en la solicitud POST.
     * Antes de eliminar, verifica si el area esta en uso por algun empleado.
     * Responde con un codigo HTTP 200 (OK) si la eliminacion fue exitosa,
     * 404 (Not Found) si el ID no existe, 409 (Conflict) si el area esta en uso,
     * o 500 en caso de error.
     *
     * Datos esperados en $_POST:
     * - 'id' (int): El ID del area a eliminar.
     */
    private function eliminar() {
        $id = intval($_POST['id'] ?? 0);

        if (!$id) {
            http_response_code(400); // Bad Request
            echo "ID invalido para eliminacion.";
            return;
        }

        // Validacion de Uso
        $empleadosUsandoArea = $this->estaAreaEnUso($id);

        if ($empleadosUsandoArea > 0) {
            http_response_code(409); // Conflict
            echo "No se puede eliminar el area porque esta asignada a " . $empleadosUsandoArea . " empleado(s).";
            return;
        } elseif ($empleadosUsandoArea === -1) {
            // Error interno al verificar el uso
            http_response_code(500);
            echo "Error interno al verificar si el area esta en uso.";
            return;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM areas WHERE ID=?");
            if ($stmt === false) {
                throw new Exception("Error en la preparacion de la consulta: " . $this->conn->error);
            }
            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                http_response_code(404); // Not Found
                echo "No se encontro el area con el ID proporcionado para eliminar.";
            } else {
                http_response_code(200); // OK
                echo "Area eliminada exitosamente.";
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            error_log("Error al eliminar area: " . $e->getMessage());
            echo "Error al eliminar el area: " . $e->getMessage();
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al eliminar area: " . $e->getMessage());
            echo "Error inesperado al eliminar el area.";
        }
    }

    /**
     * Lista todas las areas disponibles en la base de datos.
     *
     * No espera parametros.
     * Establece el encabezado 'Content-Type' a 'application/json' y devuelve un array JSON
     * de objetos de area (cada uno con 'ID' y 'NOMBRE').
     * Responde con un codigo HTTP 200 (OK) si es exitoso o 500 en caso de error.
     */
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
            error_log("Error al listar areas: " . $e->getMessage());
            echo json_encode(["error" => "Error al listar areas: " . $e->getMessage()]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log("Error general al listar areas: " . $e->getMessage());
            echo json_encode(["error" => "Error inesperado al listar areas: " . $e->getMessage()]);
        }
    }

    /**
     * Destructor de la clase AreaController.
     *
     * Cierra la conexion a la base de datos cuando el objeto es destruido.
     */
    public function __destruct() {
        if ($this->conn) { // Asegurarse de que la conexion exista antes de intentar cerrarla
            $this->conn->close();
        }
    }
}

/*

## Manejo de Solicitudes HTTP

Este bloque de codigo se encarga de la logica principal para procesar las solicitudes 
HTTP (GET y POST) y utiliza la clase `AreaController` para interactuar con la base de datos.

### Inicializacion y Comprobacion de Conexion 🚦

Antes de cualquier operacion, se verifica que la conexion a la base de datos (`$conn`) haya sido establecida
por el archivo `conexion.php`. Si la conexion falla, se establece un **codigo de respuesta HTTP 500** (`Internal Server Error`) 
y se envia un mensaje de error critico al cliente antes de terminar la ejecucion del script.

### Solicitudes POST

Si la solicitud es de tipo **POST**, se espera un parametro `accion` en `$_POST` para determinar la operacion a realizar:

-   **`crear`**:
    -   **Datos esperados**: `$_POST['nombre']` (string), que es el nombre de la nueva area.
    -   **Funcion llamada**: `$controller->crear()`.
    -   **Proposito**: Añadir una nueva area a la tabla `areas`.

-   **`editar`**:
    -   **Datos esperados**: `$_POST['id']` (int), el ID del area a modificar, y `$_POST['nombre']` (string), el nuevo nombre del area.
    -   **Funcion llamada**: `$controller->editar()`.
    -   **Proposito**: Actualizar el nombre de un area existente.

-   **`eliminar`**:
    -   **Datos esperados**: `$_POST['id']` (int), el ID del area a eliminar.
    -   **Funcion llamada**: `$controller->eliminar()`.
    -   **Proposito**: Remover un area de la tabla `areas`, siempre y cuando no este en uso por ningun empleado.

-   **`default`**:
    -   Si la `accion` no es reconocida, se envia un **codigo de respuesta HTTP 400** (`Bad Request`) 
    y un mensaje indicando que la accion no es valida.

### Solicitudes GET

Si la solicitud es de tipo **GET**, el sistema procede a listar las areas:

-   **Datos esperados**: Ninguno en particular para la operacion de listado general.
-   **Funcion llamada**: `$controller->listar()`.
-   **Proposito**: Recuperar todas las areas almacenadas en la base de datos.
-   **Respuesta**: El encabezado `Content-Type` se establece a `application/json` y se devuelve 
      un **array JSON** de objetos de area, cada uno con su `ID` y `NOMBRE`.

### Cierre de Conexion

Al finalizar la ejecucion del script, el **destructor** de la clase `AreaController` (`__destruct()`)
 se encarga de cerrar la conexion a la base de datos (`$this->conn->close()`) para liberar los recursos.
*/