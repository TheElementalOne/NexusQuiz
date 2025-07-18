class RolManager {
  constructor() {
    this.apiUrl = '../cruds/crudRoles.php';
    this.tabla = document.getElementById('tablaRoles');
    this.formCrear = document.getElementById('formRol');
    this.formEditar = document.getElementById('formEditarRol');

    this.formCrear.addEventListener('submit', this.crearRol.bind(this));
    this.formEditar.addEventListener('submit', this.guardarEdicion.bind(this));

    this.cargarRoles();
  }

  async cargarRoles() {
    const res = await fetch(this.apiUrl);
    const data = await res.json();
    this.tabla.innerHTML = '';

    data.forEach(rol => {
      this.tabla.innerHTML += `
        <tr>
          <td>${rol.ID}</td>
          <td>${rol.NOMBRE}</td>
          <td>
            <button onclick="rolManager.mostrarModalEdicion(${rol.ID}, '${rol.NOMBRE}')">Editar</button>
            <button onclick="rolManager.eliminar(${rol.ID})">Eliminar</button>
          </td>
        </tr>`;
    });
  }

  async crearRol(e) {
    e.preventDefault();
    const nombre = document.getElementById('crear_nombre_rol').value.trim();

    if (!this.validarNombre(nombre)) {
      Swal.fire({
        icon: 'error',
        title: 'Nombre inválido',
        text: 'Solo se permiten letras, números, guiones y espacios. Máximo 250 caracteres.',
        confirmButtonText: 'Aceptar'
      });
      return;
    }

    const datos = new URLSearchParams();
    datos.append('accion', 'crear');
    datos.append('nombre', nombre);

    const res = await fetch(this.apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: datos.toString()
    });

    const msg = await res.text();
    Swal.fire({
      icon: 'success',
      title: 'Rol creado exitosamente',
      text: msg,
      confirmButtonText: 'Aceptar'
    }).then(() => location.reload());
  }

  mostrarModalEdicion(id, nombre) {
    document.getElementById('editarId').value = id;
    document.getElementById('editar_nombre_rol').value = nombre;
    document.getElementById('modalEditar').style.display = 'flex';
  }

  async guardarEdicion(e) {
    e.preventDefault();

    const id = document.getElementById('editarId').value;
    const nombre = document.getElementById('editar_nombre_rol').value.trim();

    if (!this.validarNombre(nombre)) {
      Swal.fire({
        icon: 'error',
        title: 'Nombre inválido',
        text: 'Solo se permiten letras, números, guiones y espacios. Máximo 250 caracteres.',
        confirmButtonText: 'Aceptar'
      });
      return;
    }

    const datos = new URLSearchParams();
    datos.append('accion', 'editar');
    datos.append('id', id);
    datos.append('nombre', nombre);

    const res = await fetch(this.apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: datos.toString()
    });

    const msg = await res.text();
    Swal.fire({
      icon: 'success',
      title: 'Rol actualizado correctamente',
      text: msg,
      confirmButtonText: 'Aceptar'
    }).then(() => location.reload());
  }

  async eliminar(id) {
    const confirmacion = await Swal.fire({
      title: '¿Estás seguro?',
      text: 'Esta acción eliminará el rol permanentemente.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    });

    if (confirmacion.isConfirmed) {
      const datos = new URLSearchParams();
      datos.append('accion', 'eliminar');
      datos.append('id', id);

      const res = await fetch(this.apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: datos.toString()
      });

      const msg = await res.text();
      Swal.fire({
        icon: 'success',
        title: 'Eliminado',
        text: msg,
        confirmButtonText: 'Aceptar'
      }).then(() => location.reload());
    }
  }

  cerrarModal() {
    this.formEditar.reset();
    document.getElementById('modalEditar').style.display = 'none';
  }

  cancelarEdicion() {
    this.formCrear.reset();
  }

  validarNombre(nombre) {
    const regex = /^[a-zA-Z0-9\- ]+$/;
    return regex.test(nombre) && nombre.length > 0 && nombre.length <= 250;
  }
}

// Instanciar clase
let rolManager;
document.addEventListener('DOMContentLoaded', () => {
  rolManager = new RolManager();
});
