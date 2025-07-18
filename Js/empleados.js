class EmpleadoManager {
  constructor() {
    this.apiUrl = "../cruds/crudEmpleados.php";
    this.tabla = document.getElementById("tablaEmpleados");

    // Formulario crear
    this.formCrear = document.getElementById("formEmpleado");
    this.inputNombre = document.getElementById("crear_nombre_empleado");
    this.inputEmail = document.getElementById("crear_email_empleado");
    this.selectArea = document.getElementById("crear_area_empleado");
    this.checkBoletin = document.getElementById("crear_boletin_empleado");
    this.radioSexo = document.getElementsByName("sexo");
    this.textDescripcion = document.getElementById(
      "crear_descripcion_empleado"
    );
    this.containerRoles = document.getElementById("contenedor_roles_empleado");

    // Formulario editar
    this.formEditar = document.getElementById("formEditarEmpleado");
    this.modalEditar = document.getElementById("modalEditar");
    this.btnCerrarModal = document.getElementById("cerrarModalEditar");

    this.formCrear.addEventListener("submit", this.crearEmpleado.bind(this));
    this.formEditar.addEventListener(
      "submit",
      this.actualizarEmpleado.bind(this)
    );
    this.btnCerrarModal.addEventListener("click", this.cerrarModal.bind(this));

    this.cargarAreas();
    this.cargarRoles();
    this.cargarEmpleados();
  }

  async cargarEmpleados() {
    const res = await fetch(this.apiUrl);
    const data = await res.json();
    this.tabla.innerHTML = "";
    const MAX_LENGTH_NOMBRE = 30;
    const MAX_LENGTH_EMAIL = 30;
    const MAX_LENGTH_AREA = 20;

    data.forEach((emp) => {
      //tooltip para Nombre, Email y Área
      const nombreDisplay = this.truncateText(emp.NOMBRE, MAX_LENGTH_NOMBRE);
      const emailDisplay = this.truncateText(emp.EMAIL, MAX_LENGTH_EMAIL);
      const areaDisplay = this.truncateText(emp.AREA, MAX_LENGTH_AREA);

      this.tabla.innerHTML += `
        <tr>
          <td>${emp.ID}</td>
          <td title="${emp.NOMBRE}">${nombreDisplay}</td>
          <td title="${emp.EMAIL}">${emailDisplay}</td>
          <td>${emp.SEXO === "M" ? "Masculino" : "Femenino"}</td>
          <td title="${emp.AREA}">${areaDisplay}</td>
          <td>${emp.BOLETIN == 1 ? "Sí" : "No"}</td>
          <td>
            <button class="btn-editar" data-id="${
              emp.ID
            }"><i class="fa-solid fa-pen-to-square"></i></button>
          </td>
          <td>
            <button class="btn-eliminar" data-id="${
              emp.ID
            }"><i class="fa-solid fa-trash-can"></i></button>
          </td>
        </tr>
      `;
    });
    document.querySelectorAll(".btn-editar").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const id = e.currentTarget.dataset.id;
        this.abrirModal(id);
      });
    });

    // Nuevo: Listener para el botón de eliminar
    document.querySelectorAll(".btn-eliminar").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const id = e.currentTarget.dataset.id;
        this.eliminarEmpleado(id);
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

  async cargarAreas() {
    const res = await fetch("../cruds/crudAreas.php");
    const data = await res.json();

    const selectsArea = [
      this.selectArea,
      document.getElementById("editar_area_empleado"),
    ];

    selectsArea.forEach((select) => {
      select.innerHTML = '<option value="">Seleccione un área</option>';
      data.forEach((area) => {
        select.innerHTML += `<option value="${area.ID}">${area.ID} - ${area.NOMBRE}</option>`;
      });
    });
  }

  async cargarRoles() {
    const res = await fetch("../cruds/crudRoles.php");
    const data = await res.json();
    this.containerRoles.innerHTML = "";
    data.forEach((rol) => {
      const checkbox = `
        <span>
            <input class="input-checkbox" type="checkbox" name="crear_roles[]" value="${rol.ID}"> <span>${rol.NOMBRE}</span>
        </span>`;
      this.containerRoles.innerHTML += checkbox;
    });
  }

  async cargarRolesEditar(rolesEmpleado = []) {
    const rolesEmpleadoStrings = rolesEmpleado.map((id) => id.toString());
    const res = await fetch("../cruds/crudRoles.php");
    const roles = await res.json();
    const contenedor = document.getElementById(
      "editar_contenedor_roles_empleado"
    );
    contenedor.innerHTML = "";
    roles.forEach((rol) => {
      const rolIdAsString = rol.ID.toString();
      const isChecked = rolesEmpleadoStrings.includes(rolIdAsString);
      const checkedAttribute = isChecked ? "checked" : "";
      contenedor.innerHTML += `
        <span>
          <input class="input-checkbox" type="checkbox" name="editar_roles[]" value="${rol.ID}" ${checkedAttribute}> <span>${rol.NOMBRE}</span>
        </span>`;
    });
  }

  async abrirModal(empleadoId) {
    try {
      const res = await fetch(`${this.apiUrl}?id=${empleadoId}`);
      const data = await res.json();

      if (!data || !data.ID) {
        throw new Error("No se encontraron los datos del empleado.");
      }
      this.modalEditar.style.display = "flex";
      await Promise.all([
        this.cargarAreas(),
        this.cargarRolesEditar(data.ROLES),
      ]);

      document.getElementById("editar_id_empleado").value = data.ID;
      document.getElementById("editar_nombre_empleado").value = data.NOMBRE;
      document.getElementById("editar_email_empleado").value = data.EMAIL;
      document.getElementById("editar_area_empleado").value = data.AREA_ID;
      document.getElementById("editar_boletin_empleado").checked =
        data.BOLETIN == 1;
      document.getElementById("editar_descripcion_empleado").value =
        data.DESCRIPCION;
      document
        .querySelectorAll('input[name="editar_sexo"]')
        .forEach((radio) => {
          radio.checked = radio.value === data.SEXO;
        });
    } catch (error) {
      Swal.fire({
        icon: "error",
        title: "Error al cargar empleado",
        text: error.message,
      });
      this.cerrarModal();
    }
  }

  cerrarModal() {
    this.modalEditar.style.display = "none";
  }

  async crearEmpleado(e) {
    e.preventDefault();
    const datos = this.obtenerDatosFormulario("crear");
    const errores = this.validarCampos(datos);
    if (errores.length > 0) {
      this.mostrarErrores(errores);
      return;
    }

    const payload = this.formatearDatos("crear", datos);
    const res = await fetch(this.apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: payload.toString(),
    });
    const msg = await res.text();
    Swal.fire({
      icon: "success",
      title: "Empleado creado correctamente",
      text: msg,
      confirmButtonText: "Aceptar",
    }).then(() => location.reload());
  }

  async actualizarEmpleado(e) {
    e.preventDefault();
    const datos = this.obtenerDatosFormulario("editar");
    const errores = this.validarCampos(datos);
    if (errores.length > 0) {
      this.mostrarErrores(errores);
      return;
    }

    const payload = this.formatearDatos("editar", datos);
    const res = await fetch(this.apiUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: payload.toString(),
    });
    const msg = await res.text();
    Swal.fire({
      icon: "success",
      title: "Empleado actualizado correctamente",
      text: msg,
      confirmButtonText: "Aceptar",
    }).then(() => location.reload());
  }

  // Nuevo método para eliminar empleado
  async eliminarEmpleado(id) {
    Swal.fire({
      title: "¿Estás seguro?",
      text: "¡No podrás revertir esto!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Sí, eliminarlo!",
      cancelButtonText: "Cancelar",
    }).then(async (result) => {
      if (result.isConfirmed) {
        const payload = new URLSearchParams();
        payload.append("accion", "eliminar");
        payload.append("id", id);
        try {
          const res = await fetch(this.apiUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: payload.toString(),
          });
          const msg = await res.text();
          Swal.fire(
            "¡Eliminado!",
            "El empleado ha sido eliminado.",
            "success"
          ).then(() => location.reload());
        } catch (error) {
          Swal.fire(
            "Error",
            "Hubo un problema al eliminar el empleado.",
            "error"
          );
        }
      }
    });
  }

  obtenerDatosFormulario(tipo) {
    const prefix = tipo === "crear" ? "crear" : "editar";
    const form =
      tipo === "crear"
        ? this.formCrear
        : document.getElementById("formEditarEmpleado");
    const formData = new FormData(form);

    const data = {
      id: formData.get("id"),
      nombre: formData.get("nombre"),
      email: formData.get("email"),
      sexo: formData.get(`${prefix === "crear" ? "sexo" : "editar_sexo"}`),
      area_id: formData.get("area_id"),
      boletin: formData.get("boletin") ? 1 : 0,
      descripcion: formData.get("descripcion"),
      rolesSeleccionados: formData.getAll(`${prefix}_roles[]`),
    };
    return data;
  }

  formatearDatos(accion, datos) {
    const formData = new URLSearchParams();
    formData.append("accion", accion);
    if (accion === "editar") formData.append("id", datos.id);
    formData.append("nombre", datos.nombre);
    formData.append("email", datos.email);
    formData.append("sexo", datos.sexo);
    formData.append("area_id", datos.area_id);
    formData.append("boletin", datos.boletin);
    formData.append("descripcion", datos.descripcion);
    datos.rolesSeleccionados.forEach((r) => formData.append("roles[]", r));
    return formData;
  }

  validarCampos({
    nombre,
    email,
    sexo,
    area_id,
    descripcion,
    rolesSeleccionados,
  }) {
    const errores = [];
    if (!nombre || !this.validarNombre(nombre)) {
      errores.push(
        "El nombre es obligatorio, solo permite letras y espacios, máximo 250 caracteres."
      );
    }
    if (!email || !this.validarEmail(email)) {
      errores.push(
        "El correo es obligatorio y debe tener formato válido (máximo 250 caracteres)."
      );
    }
    if (!["M", "F"].includes(sexo)) {
      errores.push(
        "Debe seleccionar un sexo válido: Masculino (M) o Femenino (F)."
      );
    }
    if (!area_id) {
      errores.push("Debe seleccionar un área.");
    }
    // Modificado: Ahora se usa validarLongitud
    if (!this.validarLongitud(descripcion, 0, 500)) {
      errores.push("La descripción no puede superar los 500 caracteres.");
    }
    if (rolesSeleccionados.length === 0) {
      errores.push("Debe seleccionar al menos un rol.");
    }
    return errores;
  }

  mostrarErrores(errores) {
    Swal.fire({
      icon: "error",
      title: "Errores en el formulario",
      html: `<ul style="text-align: left;">${errores
        .map((e) => `<li>${e}</li>`)
        .join("")}</ul>`,
      confirmButtonText: "Corregir",
    });
  }

  validarNombre(nombre) {
    return /^[a-zA-ZÁÉÍÓÚáéíóúÑñ ]+$/.test(nombre) && nombre.length <= 250;
  }

  validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email) && email.length <= 250;
  }

  // Nuevo método para validar longitud de texto
  validarLongitud(texto, min, max) {
    return texto.length >= min && texto.length <= max;
  }
}

let empleadoManager;
document.addEventListener("DOMContentLoaded", () => {
  empleadoManager = new EmpleadoManager();
});
