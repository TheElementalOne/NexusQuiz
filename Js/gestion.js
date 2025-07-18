// --- UTILIDADES GLOBALES ---
const MAX_LENGTH_NOMBRE_TABLA = 30; // Define el máximo de caracteres para el nombre en la tabla

function truncateText(text, maxLength) {
    if (text.length > maxLength) {
        return text.substring(0, maxLength) + "...";
    }
    return text;
}

// --- CLASE AreaManager ---
class AreaManager {
    constructor() {
        this.apiUrl = "../cruds/crudAreas.php";
        this.tabla = document.getElementById("tablaAreas");
        this.formCrear = document.getElementById("formArea");
        this.formEditar = document.getElementById("formEditarArea");
        this.modalEditar = document.getElementById("modalEditarArea"); // ID del modal específico de áreas

        this.formCrear.addEventListener("submit", this.crearArea.bind(this));
        this.formEditar.addEventListener("submit", this.guardarEdicion.bind(this));

        this.cargarAreas();
    }

    async cargarAreas() {
        const res = await fetch(this.apiUrl);
        if (!res.ok) {
            console.error('Error al cargar áreas:', res.status, res.statusText);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudieron cargar las áreas del servidor.',
                confirmButtonText: 'Aceptar'
            });
            return;
        }
        const data = await res.json();
        this.tabla.innerHTML = "";

        data.forEach((area) => {
            const nombreDisplay = truncateText(area.NOMBRE, MAX_LENGTH_NOMBRE_TABLA);
            this.tabla.innerHTML += `
                <tr>
                    <td data-label="ID">${area.ID}</td>
                    <td data-label="Nombre" title="${area.NOMBRE}">${nombreDisplay}</td>
                    <td data-label="Acciones" class="acciones">
                        <button class="btn btn-editar" data-id="${area.ID}" data-nombre="${area.NOMBRE}">Editar</button>
                        <button class="btn btn-eliminar" data-id="${area.ID}">Eliminar</button>
                    </td>
                </tr>`;
        });

        this.tabla.querySelectorAll(".btn-editar").forEach((button) => {
            button.addEventListener("click", (e) => {
                const id = e.target.dataset.id;
                const nombre = e.target.dataset.nombre;
                this.mostrarModalEdicion(id, nombre);
            });
        });

        this.tabla.querySelectorAll(".btn-eliminar").forEach((button) => {
            button.addEventListener("click", (e) => {
                const id = e.target.dataset.id;
                this.eliminar(id);
            });
        });
    }

    async crearArea(e) {
        e.preventDefault();
        const nombre = document.getElementById("crear_nombre_area").value.trim();

        if (!this.validarNombre(nombre)) {
            return;
        }

        const datos = new URLSearchParams();
        datos.append("accion", "crear");
        datos.append("nombre", nombre);

        try {
            const res = await fetch(this.apiUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: datos.toString(),
            });

            const msg = await res.text();
            if (res.ok) {
                Swal.fire({
                    icon: "success",
                    title: "Área creada exitosamente",
                    text: msg,
                    confirmButtonText: "Aceptar",
                }).then(() => {
                    this.formCrear.reset(); // Limpiar formulario después de éxito
                    this.cargarAreas(); // Recargar la tabla
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error al crear área",
                    text: msg,
                    confirmButtonText: "Aceptar",
                });
            }
        } catch (error) {
            console.error('Error de red al crear área:', error);
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No se pudo conectar con el servidor para crear el área.",
                confirmButtonText: "Aceptar",
            });
        }
    }

    mostrarModalEdicion(id, nombre) {
        document.getElementById("editarIdArea").value = id; // ID del input oculto específico de área
        document.getElementById("editar_nombre_area").value = nombre; // ID del input de nombre específico de área
        this.modalEditar.style.display = "flex";
    }

    async guardarEdicion(e) {
        e.preventDefault();

        const id = document.getElementById("editarIdArea").value;
        const nombre = document.getElementById("editar_nombre_area").value.trim();

        if (!this.validarNombre(nombre)) {
            return;
        }

        const datos = new URLSearchParams();
        datos.append("accion", "editar");
        datos.append("id", id);
        datos.append("nombre", nombre);

        try {
            const res = await fetch(this.apiUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: datos.toString(),
            });

            const msg = await res.text();
            if (res.ok) {
                Swal.fire({
                    icon: "success",
                    title: "Área actualizada correctamente",
                    text: msg,
                    confirmButtonText: "Aceptar",
                }).then(() => {
                    this.cerrarModal(); // Cerrar modal después de éxito
                    this.cargarAreas(); // Recargar la tabla
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error al actualizar área",
                    text: msg,
                    confirmButtonText: "Aceptar",
                });
            }
        } catch (error) {
            console.error('Error de red al actualizar área:', error);
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No se pudo conectar con el servidor para actualizar el área.",
                confirmButtonText: "Aceptar",
            });
        }
    }

    async eliminar(id) {
        const confirmacion = await Swal.fire({
            title: "¿Estás seguro?",
            text: "Esta acción eliminará el área permanentemente.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar",
            reverseButtons: true,
        });

        if (confirmacion.isConfirmed) {
            const datos = new URLSearchParams();
            datos.append("accion", "eliminar");
            datos.append("id", id);

            try {
                const res = await fetch(this.apiUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: datos.toString(),
                });

                // *** CAMBIO CLAVE AQUÍ: Manejar respuestas HTTP status codes ***
                if (res.ok) { // res.ok es true para status 200-299
                    const msg = await res.text();
                    Swal.fire({
                        icon: "success",
                        title: "Eliminado",
                        text: msg,
                        confirmButtonText: "Aceptar",
                    }).then(() => {
                        this.cargarAreas();
                    });
                } else {
                    // Si la respuesta no es OK, obtenemos el mensaje de error del servidor
                    const errorMsg = await res.text();
                    Swal.fire({
                        icon: "error",
                        title: "Error al eliminar área",
                        text: errorMsg, // Mostrar el mensaje específico del servidor
                        confirmButtonText: "Aceptar",
                    });
                }
            } catch (error) {
                console.error('Error de red al eliminar área:', error);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "No se pudo conectar con el servidor para eliminar el área.",
                    confirmButtonText: "Aceptar",
                });
            }
        }
    }

    cerrarModal() {
        this.formEditar.reset();
        this.modalEditar.style.display = "none";
    }

    // Esta función se mantiene global o se puede integrar en la clase si se desea
    cancelarEdicion() { // Este es para el formulario de crear área
        this.formCrear.reset();
    }

    validarNombre(nombre) {
        const regex = /^[a-zA-Z0-9\- ]+$/;
        const maxLength = 250;

        if (nombre.length === 0) {
            Swal.fire({
                icon: "error",
                title: "Nombre vacío",
                text: "El nombre no puede estar vacío.",
                confirmButtonText: "Aceptar",
            });
            return false;
        }

        if (nombre.length > maxLength) {
            Swal.fire({
                icon: "error",
                title: "Nombre demasiado largo",
                text: `Máximo permitido: ${maxLength} caracteres.`,
                confirmButtonText: "Aceptar",
            });
            return false;
        }

        if (!regex.test(nombre)) {
            Swal.fire({
                icon: "error",
                title: "Nombre inválido",
                text: "Solo se permiten letras, números, guiones y espacios.",
                confirmButtonText: "Aceptar",
            });
            return false;
        }
        return true;
    }
}

// --- CLASE RolManager ---
class RolManager {
    constructor() {
        this.apiUrl = '../cruds/crudRoles.php';
        this.tabla = document.getElementById('tablaRoles');
        this.formCrear = document.getElementById('formRol');
        this.formEditar = document.getElementById('formEditarRol');
        this.modalEditar = document.getElementById('modalEditarRol'); // ID del modal específico de roles

        this.formCrear.addEventListener('submit', this.crearRol.bind(this));
        this.formEditar.addEventListener('submit', this.guardarEdicion.bind(this));

        this.cargarRoles();
    }

    async cargarRoles() {
        const res = await fetch(this.apiUrl);
        if (!res.ok) {
            console.error('Error al cargar roles:', res.status, res.statusText);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudieron cargar los roles del servidor.',
                confirmButtonText: 'Aceptar'
            });
            return;
        }
        const data = await res.json();
        this.tabla.innerHTML = '';

        data.forEach(rol => {
            const nombreDisplay = truncateText(rol.NOMBRE, MAX_LENGTH_NOMBRE_TABLA);
            this.tabla.innerHTML += `
                <tr>
                    <td data-label="ID">${rol.ID}</td>
                    <td data-label="Nombre" title="${rol.NOMBRE}">${nombreDisplay}</td>
                    <td data-label="Acciones" class="acciones">
                        <button class="btn btn-editar" data-id="${rol.ID}" data-nombre="${rol.NOMBRE}">Editar</button>
                        <button class="btn btn-eliminar" data-id="${rol.ID}">Eliminar</button>
                    </td>
                </tr>`;
        });

        this.tabla.querySelectorAll('.btn-editar').forEach(button => {
            button.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                const nombre = e.target.dataset.nombre;
                this.mostrarModalEdicion(id, nombre);
            });
        });

        this.tabla.querySelectorAll('.btn-eliminar').forEach(button => {
            button.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                this.eliminar(id);
            });
        });
    }

    async crearRol(e) {
        e.preventDefault();
        const nombre = document.getElementById('crear_nombre_rol').value.trim();

        if (!this.validarNombre(nombre)) {
            return;
        }

        const datos = new URLSearchParams();
        datos.append('accion', 'crear');
        datos.append('nombre', nombre);

        try {
            const res = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: datos.toString()
            });

            const msg = await res.text();
            if (res.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Rol creado exitosamente',
                    text: msg,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    this.formCrear.reset();
                    this.cargarRoles();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al crear rol',
                    text: msg,
                    confirmButtonText: 'Aceptar'
                });
            }
        } catch (error) {
            console.error('Error de red al crear rol:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor para crear el rol.',
                confirmButtonText: 'Aceptar'
            });
        }
    }

    mostrarModalEdicion(id, nombre) {
        document.getElementById('editarIdRol').value = id; // ID del input oculto específico de rol
        document.getElementById('editar_nombre_rol').value = nombre; // ID del input de nombre específico de rol
        this.modalEditar.style.display = 'flex';
    }

    async guardarEdicion(e) {
        e.preventDefault();

        const id = document.getElementById('editarIdRol').value;
        const nombre = document.getElementById('editar_nombre_rol').value.trim();

        if (!this.validarNombre(nombre)) {
            return;
        }

        const datos = new URLSearchParams();
        datos.append('accion', 'editar');
        datos.append('id', id);
        datos.append('nombre', nombre);

        try {
            const res = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: datos.toString()
            });

            const msg = await res.text();
            if (res.ok) {
                Swal.fire({
                    icon: 'success',
                    title: 'Rol actualizado correctamente',
                    text: msg,
                    confirmButtonText: 'Aceptar'
                }).then(() => {
                    this.cerrarModal();
                    this.cargarRoles();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al actualizar rol',
                    text: msg,
                    confirmButtonText: 'Aceptar'
                });
            }
        } catch (error) {
            console.error('Error de red al actualizar rol:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor para actualizar el rol.',
                confirmButtonText: 'Aceptar'
            });
        }
    }

    async eliminar(id) {
        const confirmacion = await Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción eliminará el rol permanentemente.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        });

        if (confirmacion.isConfirmed) {
            const datos = new URLSearchParams();
            datos.append('accion', 'eliminar');
            datos.append('id', id);

            try {
                const res = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: datos.toString()
                });

                // *** CAMBIO CLAVE AQUÍ: Manejar respuestas HTTP status codes ***
                if (res.ok) { // res.ok es true para status 200-299
                    const msg = await res.text();
                    Swal.fire({
                        icon: 'success',
                        title: 'Eliminado',
                        text: msg,
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        this.cargarRoles();
                    });
                } else {
                    // Si la respuesta no es OK, obtenemos el mensaje de error del servidor
                    const errorMsg = await res.text();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error al eliminar rol',
                        text: errorMsg, // Mostrar el mensaje específico del servidor
                        confirmButtonText: 'Aceptar'
                    });
                }
            } catch (error) {
                console.error('Error de red al eliminar rol:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor para eliminar el rol.',
                    confirmButtonText: 'Aceptar'
                });
            }
        }
    }

    cerrarModal() {
        this.formEditar.reset();
        this.modalEditar.style.display = 'none';
    }

    // Esta función se mantiene global o se puede integrar en la clase si se desea
    cancelarEdicion() { // Este es para el formulario de crear rol
        this.formCrear.reset();
    }

    validarNombre(nombre) {
        const regex = /^[a-zA-Z0-9\- ]+$/;
        const maxLength = 250;

        if (nombre.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Nombre vacío',
                text: 'El nombre no puede estar vacío.',
                confirmButtonText: 'Aceptar'
            });
            return false;
        }

        if (nombre.length > maxLength) {
            Swal.fire({
                icon: 'error',
                title: 'Nombre demasiado largo',
                text: `Máximo permitido: ${maxLength} caracteres.`,
                confirmButtonText: 'Aceptar'
            });
            return false;
        }

        if (!regex.test(nombre)) {
            Swal.fire({
                icon: 'error',
                title: 'Nombre inválido',
                text: 'Solo se permiten letras, números, guiones y espacios.',
                confirmButtonText: 'Aceptar'
            });
            return false;
        }
        return true;
    }
}


// --- LÓGICA DE GESTIÓN DE PESTAÑAS Y Carga de Managers ---
let areaManager;
let rolManager;

document.addEventListener("DOMContentLoaded", () => {
    areaManager = new AreaManager();
    rolManager = new RolManager();

    // Inicializar lógica de pestañas
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetTab = button.dataset.tab;

            // Remover 'active' de todos los botones y contenidos
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Añadir 'active' al botón y contenido correctos
            button.classList.add('active');
            document.getElementById(`${targetTab}-tab-content`).classList.add('active');

            // Opcional: Recargar datos de la pestaña activa si es necesario
            // Esto es útil si los datos pueden cambiar en segundo plano
            if (targetTab === 'areas') {
                areaManager.cargarAreas();
            } else if (targetTab === 'roles') {
                rolManager.cargarRoles();
            }
        });
    });

    // Asegurarse de que la primera pestaña esté activa al cargar
    // Esto lo puedes controlar también por CSS con un '.active' inicial
    if (tabButtons.length > 0 && tabContents.length > 0) {
        tabButtons[0].classList.add('active');
        tabContents[0].classList.add('active');
    }
});