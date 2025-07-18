class AreaManager {
  constructor() {
    this.apiUrl = "../cruds/crudAreas.php";
    this.tabla = document.getElementById("tablaAreas");
    this.formCrear = document.getElementById("formArea");
    this.formEditar = document.getElementById("formEditarArea");
    this.modalEditar = document.getElementById("modalEditar"); // Referencia al modal

    this.formCrear.addEventListener("submit", this.crearArea.bind(this));
    this.formEditar.addEventListener("submit", this.guardarEdicion.bind(this));
    this.cargarAreas();
  }

  async cargarAreas() {
    const res = await fetch(this.apiUrl);
    const data = await res.json();
    this.tabla.innerHTML = "";

    const MAX_LENGTH_NOMBRE_AREA = 30; //máximo de caracteres para el nombre de área

    data.forEach((area) => {
      //añadir tooltip para el Nombre del Área
      const nombreDisplay = this.truncateText(
        area.NOMBRE,
        MAX_LENGTH_NOMBRE_AREA
      );

      this.tabla.innerHTML += `
        <tr>
          <td data-label="ID">${area.ID}</td>
          <td data-label="Nombre" title="${area.NOMBRE}">${nombreDisplay}</td>
          <td data-label="Acciones" class="acciones">
            <button class="btn-editar" data-id="${area.ID}" data-nombre="${area.NOMBRE}">Editar</button>
            <button class="btn-eliminar" data-id="${area.ID}">Eliminar</button>
          </td>
        </tr>`;
    });

    // Delegación de eventos para los botones de editar y eliminar
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

  // Método para truncar texto y añadir puntos suspensivos
  truncateText(text, maxLength) {
    if (text.length > maxLength) {
      return text.substring(0, maxLength) + "...";
    }
    return text;
  }

  async crearArea(e) {
    e.preventDefault();
    const nombreInput = document.getElementById("crear_nombre_area");
    const nombre = nombreInput.value.trim();

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
        // validar si la respuesta HTTP fue exitosa
        Swal.fire({
          icon: "success",
          title: "Área creada exitosamente",
          text: msg,
          confirmButtonText: "Aceptar",
        }).then(() => location.reload());
      } else {
        Swal.fire({
          icon: "error",
          title: "Error al crear área",
          text: msg,
          confirmButtonText: "Aceptar",
        });
      }
    } catch (error) {
      Swal.fire({
        icon: "error",
        title: "Error de conexión",
        text: "No se pudo conectar con el servidor para crear el área.",
        confirmButtonText: "Aceptar",
      });
    }
  }

  mostrarModalEdicion(id, nombre) {
    document.getElementById("editarId").value = id;
    document.getElementById("editar_nombre_area").value = nombre;
    this.modalEditar.style.display = "flex"; // Cambiar propiedad a flex para mostrar modal
  }

  async guardarEdicion(e) {
    e.preventDefault();

    const id = document.getElementById("editarId").value;
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
        }).then(() => location.reload());
      } else {
        Swal.fire({
          icon: "error",
          title: "Error al actualizar área",
          text: msg,
          confirmButtonText: "Aceptar",
        });
      }
    } catch (error) {
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

        const msg = await res.text();
        if (res.ok) {
          Swal.fire({
            icon: "success",
            title: "Eliminado",
            text: msg,
            confirmButtonText: "Aceptar",
          }).then(() => location.reload());
        } else {
          Swal.fire({
            icon: "error",
            title: "Error al eliminar área",
            text: msg,
            confirmButtonText: "Aceptar",
          });
        }
      } catch (error) {
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

  cancelarEdicion() {
    this.formCrear.reset();
  }

  validarNombre(nombre) {
    const regex = /^[a-zA-Z0-9\- ]+$/;
    const maxLength = 250;

    if (nombre.length === 0) {
      Swal.fire({
        icon: "error",
        title: "Nombre vacío",
        text: "El nombre del área no puede estar vacío.",
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

// Instanciar clase
let areaManager;
document.addEventListener("DOMContentLoaded", () => {
  areaManager = new AreaManager();
});
