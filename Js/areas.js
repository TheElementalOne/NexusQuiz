class AreaManager {
  constructor() {
    this.apiUrl = "../cruds/crudAreas.php";
    this.tabla = document.getElementById("tablaAreas");
    this.formCrear = document.getElementById("formArea");
    this.formEditar = document.getElementById("formEditarArea");

    this.formCrear.addEventListener("submit", this.crearArea.bind(this));
    this.formEditar.addEventListener("submit", this.guardarEdicion.bind(this));

    this.cargarAreas();
  }

  async cargarAreas() {
    const res = await fetch(this.apiUrl);
    const data = await res.json();
    this.tabla.innerHTML = "";

    data.forEach((area) => {
      this.tabla.innerHTML += `
        <tr>
          <td>${area.ID}</td>
          <td>${area.NOMBRE}</td>
          <td>
            <button onclick="areaManager.mostrarModalEdicion(${area.ID}, '${area.NOMBRE}')">Editar</button>
            <button onclick="areaManager.eliminar(${area.ID})">Eliminar</button>
          </td>
        </tr>`;
    });
  }

  async crearArea(e) {
    e.preventDefault();
    const nombre = document.getElementById("crear_nombre_area").value.trim();

    if (!this.validarNombre(nombre)) {
      Swal.fire({
        icon: "error",
        title: "Nombre inválido",
        text: "Solo se permiten letras, números, guiones y espacios.",
        confirmButtonText: "Aceptar",
      });
      return;
    }

    const datos = new URLSearchParams();
    datos.append("accion", "crear");
    datos.append("nombre", nombre);

    const res = await fetch(this.apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: datos.toString(),
    });

    const msg = await res.text();
    Swal.fire({
      icon: "success",
      title: "Área creada exitosamente",
      text: msg,
      confirmButtonText: "Aceptar",
    }).then(() => location.reload());
  }

  mostrarModalEdicion(id, nombre) {
    document.getElementById("editarId").value = id;
    document.getElementById("editar_nombre_area").value = nombre;
    document.getElementById("modalEditar").style.display = "flex";
  }

  async guardarEdicion(e) {
    e.preventDefault();

    const id = document.getElementById("editarId").value;
    const nombre = document.getElementById("editar_nombre_area").value.trim();

    if (!this.validarNombre(nombre)) {
      Swal.fire({
        icon: "error",
        title: "Nombre inválido",
        text: "Solo se permiten letras, números, guiones y espacios.",
        confirmButtonText: "Aceptar",
      });
      return;
    }

    const datos = new URLSearchParams();
    datos.append("accion", "editar");
    datos.append("id", id);
    datos.append("nombre", nombre);

    const res = await fetch(this.apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: datos.toString(),
    });

    const msg = await res.text();
    Swal.fire({
      icon: "success",
      title: "Área actualizada correctamente",
      text: msg,
      confirmButtonText: "Aceptar",
    }).then(() => location.reload());
  }

  async eliminar(id) {
    const confirmacion = await Swal.fire({
      title: "¿Estás seguro?",
      text: "Esta acción eliminará el área permanentemente.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
    });

    if (confirmacion.isConfirmed) {
      const datos = new URLSearchParams();
      datos.append("accion", "eliminar");
      datos.append("id", id);

      const res = await fetch(this.apiUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: datos.toString(),
      });

      const msg = await res.text();
      Swal.fire({
        icon: "success",
        title: "Eliminado",
        text: msg,
        confirmButtonText: "Aceptar",
      }).then(() => location.reload());
    }
  }

  cerrarModal() {
    this.formEditar.reset();
    document.getElementById("modalEditar").style.display = "none";
  }

  cancelarEdicion() {
    this.formCrear.reset();
  }

  validarNombre(nombre) {
    const regex = /^[a-zA-Z0-9\- ]+$/;
    const maxLength = 250;

    if (nombre.length === 0) return false;

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

//Instanciar clase
let areaManager;
document.addEventListener("DOMContentLoaded", () => {
  areaManager = new AreaManager();
});
