<?php
// Sesión y configuración centralizadas
require_once __DIR__ . '/../bootstrap.php';
// Control de acceso común
require_once __DIR__ . '/../access_control.php';
// Conexión a BD
require_once __DIR__ . '/../conn.php';


function can_assign_role(string $actor, string $target): bool {

    // Solo Master puede asignar Master

    if ($target === 'Master') return $actor === 'Master';

    // Roles permitidos siempre

    return in_array($target, ['Administrativo','Tripulacion'], true);

}


// Solo los roles 'Administrativo' y 'Master' pueden acceder a esta pagina
if (!in_array($_SESSION['usuario_rol'] ?? '', ['Administrativo', 'Master'], true)) {

    // Guardar mensaje de error y redirigir

    $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para acceder a esta sección.</div>';

    header('Location: ' . BASE_URL . 'index.php');

    exit;

}



// Logica para manejar la creacion y eliminacion de usuarios

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {

        http_response_code(403);

        exit('CSRF invalido');

    }

    $accion = $_POST['accion'] ?? '';



    if ($accion === 'crear') {

        $nombres = $_POST['nombres'] ?? '';

        $apellidos = $_POST['apellidos'] ?? '';

        $id_cc = $_POST['id_cc'] ?? null;

        $id_registro = $_POST['id_registro'] ?? null;

        $usuario = $_POST['usuario'] ?? '';

        $clave = $_POST['clave'] ?? '';

        $confirmar_clave = $_POST['confirmar_clave'] ?? '';

        $rol = $_POST['rol'] ?? '';



        if ($clave !== $confirmar_clave) {

            $_SESSION['message'] = '<div class="alert alert-danger">Las contraseñas no coinciden.</div>';

        } elseif (!can_assign_role($_SESSION['usuario_rol'], $rol)) {

            $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para asignar ese rol.</div>';

        } elseif (!empty($nombres) && !empty($apellidos) && !empty($usuario) && !empty($clave) && !empty($rol)) {

            // Hashear la contraseña para mayor seguridad

            $clave_hasheada = password_hash($clave, PASSWORD_DEFAULT);



            $stmt = $conn->prepare("INSERT INTO tripulacion (nombres, apellidos, id_cc, id_registro, usuario, clave, rol) VALUES (?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param("sssssss", $nombres, $apellidos, $id_cc, $id_registro, $usuario, $clave_hasheada, $rol);



            if ($stmt->execute()) {

                $_SESSION['message'] = '<div class="alert alert-success">Usuario creado con exito.</div>';

            } else {

                if ($stmt->errno === 1062) {

                    $_SESSION['message'] = '<div class="alert alert-warning">El nombre de usuario ya existe.</div>';

                } else {

                    $_SESSION['message'] = '<div class="alert alert-danger">Error al crear el usuario.</div>';

                }

            }

            $stmt->close();

        } else {

            $_SESSION['message'] = '<div class="alert alert-danger">Todos los campos son obligatorios.</div>';

        }

    }



    if ($accion === 'editar') {

        $id_usuario = $_POST['edit_id_usuario'] ?? 0;

        $nombres = $_POST['edit_nombres'] ?? '';

        $apellidos = $_POST['edit_apellidos'] ?? '';

        $id_cc = $_POST['edit_id_cc'] ?? null;

        $id_registro = $_POST['edit_id_registro'] ?? null;

        $usuario = $_POST['edit_usuario'] ?? '';

        $rol = $_POST['edit_rol'] ?? '';



        if ($id_usuario > 0) {

            if (forbid_admin_touching_master($conn, (int)$id_usuario)) {

                $_SESSION['message'] = '<div class="alert alert-danger">No puedes modificar un usuario Master.</div>';

            } elseif (!can_assign_role($_SESSION['usuario_rol'], $rol)) {

                $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para asignar ese rol.</div>';

            } elseif ($id_usuario == $_SESSION['usuario_id'] && $rol !== $_SESSION['usuario_rol']) {

                $_SESSION['message'] = '<div class="alert alert-warning">No puedes cambiar tu propio rol.</div>';

            } else {

                $stmt = $conn->prepare("UPDATE tripulacion SET nombres = ?, apellidos = ?, id_cc = ?, id_registro = ?, usuario = ?, rol = ? WHERE id = ?");

                $stmt->bind_param("ssssssi", $nombres, $apellidos, $id_cc, $id_registro, $usuario, $rol, $id_usuario);

                if ($stmt->execute()) {

                    $_SESSION['message'] = '<div class="alert alert-success">Usuario actualizado con exito.</div>';

                }

                $stmt->close();

            }

        }

    }



    if ($accion === 'eliminar' && $_SESSION['usuario_rol'] === 'Master') {

        $id_usuario = $_POST['id_usuario'] ?? 0;

        if ($id_usuario > 0) {

            // Evitar que el usuario Master se elimine a si mismo

            if ($id_usuario == $_SESSION['usuario_id']) {

                 $_SESSION['message'] = '<div class="alert alert-warning">No puedes eliminar tu propio usuario.</div>';

            } else {

                $stmt = $conn->prepare("DELETE FROM tripulacion WHERE id = ?");

                $stmt->bind_param("i", $id_usuario);

                if ($stmt->execute()) {

                    $_SESSION['message'] = '<div class="alert alert-success">Usuario eliminado con exito.</div>';

                } else {

                    $_SESSION['message'] = '<div class="alert alert-danger">Error al eliminar el usuario.</div>';

                }

                $stmt->close();

            }

        }

    }



    if ($accion === 'cambiar_clave') {

        $id_usuario = $_POST['id_usuario_clave'] ?? 0;

        $nueva_clave = $_POST['nueva_clave'] ?? '';

        $confirmar_nueva_clave = $_POST['confirmar_nueva_clave'] ?? '';



        // Primero, verificar si el usuario a modificar es Master y si el actor es Administrativo

        $target_user_role_stmt = $conn->prepare("SELECT rol FROM tripulacion WHERE id = ?");

        $target_user_role_stmt->bind_param("i", $id_usuario);

        $target_user_role_stmt->execute();

        $target_user_role_result = $target_user_role_stmt->get_result();

        $target_user = $target_user_role_result->fetch_assoc();

        $target_user_role_stmt->close();



        if ($_SESSION['usuario_rol'] === 'Administrativo' && $target_user && $target_user['rol'] === 'Master') {

            $_SESSION['message'] = '<div class="alert alert-danger">No tienes permiso para modificar al usuario Master.</div>';

        } elseif ($nueva_clave !== $confirmar_nueva_clave) {

            $_SESSION['message'] = '<div class="alert alert-danger">Las contraseñas no coinciden.</div>';

        } elseif ($id_usuario > 0 && !empty($nueva_clave)) {

            $nueva_clave_hasheada = password_hash($nueva_clave, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE tripulacion SET clave = ? WHERE id = ?");

            $stmt->bind_param("si", $nueva_clave_hasheada, $id_usuario);

            $stmt->execute() ? $_SESSION['message'] = '<div class="alert alert-success">contraseña actualizada con éxito.</div>' : $_SESSION['message'] = '<div class="alert alert-danger">Error al actualizar la contraseña.</div>';

            $stmt->close();

        }

    }



    header('Location: ' . BASE_URL . 'admin/gestion_usuarios.php');

    exit;

}



$pageTitle = "Gestión de Usuarios";

include __DIR__ . '/../header.php';



// Generar token CSRF

if (empty($_SESSION['csrf'])) {

    $_SESSION['csrf'] = bin2hex(random_bytes(32));

}

$csrf = $_SESSION['csrf'];



// Obtener la lista de usuarios, excluyendo al Master si el rol es Administrativo

$sql_usuarios = "SELECT id, nombres, apellidos, id_cc, id_registro, usuario, rol FROM tripulacion";

if ($_SESSION['usuario_rol'] === 'Administrativo') {

    $sql_usuarios .= " WHERE rol != 'Master'";

}

$sql_usuarios .= " ORDER BY apellidos, nombres ASC";



$usuarios = $conn->query($sql_usuarios);

?>



<div class="container mt-4">

    <?php

    if (isset($_SESSION['message'])) {

        echo $_SESSION['message'];

        unset($_SESSION['message']);

    }

    ?>

    <div class="row">

        <!-- Formulario para crear usuarios -->

        <div class="col-md-4">

            <div class="card">

                <div class="card-header">

                    <h4>Crear Nuevo Usuario</h4>

                </div>

                <div class="card-body">

                    <form method="POST" action="gestion_usuarios.php">

                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                        <input type="hidden" name="accion" value="crear">

                        <div class="mb-3">

                            <label for="nombres" class="form-label">Nombres</label>

                            <input type="text" class="form-control" id="nombres" name="nombres" required>

                        </div>

                        <div class="mb-3">

                            <label for="apellidos" class="form-label">Apellidos</label>

                            <input type="text" class="form-control" id="apellidos" name="apellidos" required>

                        </div>

                        <div class="mb-3">

                            <label for="usuario" class="form-label">Nombre de Usuario</label>

                            <input type="text" class="form-control" id="usuario" name="usuario" required>

                        </div>

                        <div class="mb-3">

                            <label for="id_cc" class="form-label">Número de Cédula (CC)</label>

                            <input type="text" class="form-control" id="id_cc" name="id_cc">

                        </div>

                        <div class="mb-3">

                            <label for="id_registro" class="form-label">Número de Registro Profesional</label>

                            <input type="text" class="form-control" id="id_registro" name="id_registro">

                        </div>

                        <div class="mb-3">

                            <label for="clave" class="form-label">contraseña</label> 

                            <input type="password" class="form-control" id="clave" name="clave" required>

                        </div>

                        <div class="mb-3">

                            <label for="confirmar_clave" class="form-label">Confirmar contraseña</label>

                            <input type="password" class="form-control" id="confirmar_clave" name="confirmar_clave" required>

                        </div>

                        <div class="mb-3">

                            <label for="rol" class="form-label">Rol</label>

                            <select class="form-select" id="rol" name="rol" required>

                                <option value="Tripulacion">Tripulación</option>

                                <option value="Administrativo">Administrativo</option>

                            </select>

                        </div>

                        <button type="submit" class="btn btn-primary">Crear Usuario</button>

                    </form>

                </div>

            </div>

        </div>



        <!-- Lista de usuarios existentes -->

        <div class="col-md-8">

            <div class="card">

                <div class="card-header">

                    <h4>Usuarios Existentes</h4>

                </div>

                <div class="card-body">

                    <ul class="list-group">

                        <?php while ($usuario = $usuarios->fetch_assoc()): ?>

                            <li class="list-group-item d-flex justify-content-between align-items-center">

                                <div>

                                    <strong><?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></strong> (<?= htmlspecialchars($usuario['usuario']) ?>)

                                    <br>

                                    <small class="text-muted">CC: <?= htmlspecialchars($usuario['id_cc'] ?: 'N/A') ?></small> | 

                                    <small class="text-muted">Registro: <?= htmlspecialchars($usuario['id_registro'] ?: 'N/A') ?></small> | 

                                    <small class="text-muted">Rol: <?= htmlspecialchars($usuario['rol']) ?></small>

                                </div>

                                <div class="btn-group">

                                    <button type="button" class="btn btn-sm btn-info btn-editar" data-bs-toggle="modal" data-bs-target="#editarUsuarioModal" data-usuario='<?= htmlspecialchars(json_encode($usuario, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT), ENT_QUOTES, "UTF-8") ?>'>

                                        Editar

                                    </button>

                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#cambiarClaveModal" data-userid="<?= $usuario['id'] ?>" data-username="<?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?>">

                                        Cambiar contraseña

                                    </button>

                                    <?php if ($_SESSION['usuario_rol'] === 'Master' && $usuario['id'] != $_SESSION['usuario_id']): ?>

                                        <form method="POST" action="gestion_usuarios.php" onsubmit="return confirm('¿Estás seguro de que deseas eliminar a este usuario?');" style="display: inline;">

                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                                            <input type="hidden" name="accion" value="eliminar">

                                            <input type="hidden" name="id_usuario" value="<?= $usuario['id'] ?>">

                                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>

                                        </form>

                                    <?php endif; ?>

                                </div>

                            </li>

                        <?php endwhile; ?>

                    </ul>

                </div>

            </div>

        </div>

    </div>

</div>



<!-- Modal para Editar Usuario -->

<div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">

  <div class="modal-dialog">

    <div class="modal-content">

      <div class="modal-header">

        <h5 class="modal-title" id="editarUsuarioModalLabel">Editar Usuario</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <form method="POST" action="gestion_usuarios.php">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="modal-body">

          <input type="hidden" name="accion" value="editar">

          <input type="hidden" name="edit_id_usuario" id="edit_id_usuario">

          <div class="mb-3">

            <label for="edit_nombres" class="form-label">Nombres</label>

            <input type="text" class="form-control" id="edit_nombres" name="edit_nombres" required>

          </div>

          <div class="mb-3">

            <label for="edit_apellidos" class="form-label">Apellidos</label>

            <input type="text" class="form-control" id="edit_apellidos" name="edit_apellidos" required>

          </div>

          <div class="mb-3">

            <label for="edit_id_cc" class="form-label">Numero de Cedula (CC)</label>

            <input type="text" class="form-control" id="edit_id_cc" name="edit_id_cc">

          </div>

          <div class="mb-3">

            <label for="edit_id_registro" class="form-label">Numero de Registro Profesional</label>

            <input type="text" class="form-control" id="edit_id_registro" name="edit_id_registro">

          </div>

          <div class="mb-3">

            <label for="edit_usuario" class="form-label">Nombre de Usuario</label>

            <input type="text" class="form-control" id="edit_usuario" name="edit_usuario" required>

          </div>

          <div class="mb-3">

            <label for="edit_rol" class="form-label">Rol</label>

            <select class="form-select" id="edit_rol" name="edit_rol" required>

                <option value="Tripulacion">Tripulación</option>

                <option value="Administrativo">Administrativo</option>

            </select>

          </div>

        </div>

        <div class="modal-footer">

          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

          <button type="submit" class="btn btn-primary">Guardar Cambios</button>

        </div>

      </form>

    </div>

  </div>

</div>



<!-- Modal para Editar Usuario -->

<div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">

  <div class="modal-dialog">

    <div class="modal-content">

      <div class="modal-header">

        <h5 class="modal-title" id="editarUsuarioModalLabel">Editar Usuario</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <form method="POST" action="gestion_usuarios.php">

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="modal-body">

          <input type="hidden" name="accion" value="editar">

          <input type="hidden" name="edit_id_usuario" id="edit_id_usuario">

          <div class="mb-3">

            <label for="edit_nombres" class="form-label">Nombres</label>

            <input type="text" class="form-control" id="edit_nombres" name="edit_nombres" required>

          </div>

          <div class="mb-3">

            <label for="edit_apellidos" class="form-label">Apellidos</label>

            <input type="text" class="form-control" id="edit_apellidos" name="edit_apellidos" required>

          </div>

          <div class="mb-3">

            <label for="edit_id_cc" class="form-label">Numero de Cedula (CC)</label>

            <input type="text" class="form-control" id="edit_id_cc" name="edit_id_cc">

          </div>

          <div class="mb-3">

            <label for="edit_id_registro" class="form-label">Numero de Registro Profesional</label>

            <input type="text" class="form-control" id="edit_id_registro" name="edit_id_registro">

          </div>

          <div class="mb-3">

            <label for="edit_usuario" class="form-label">Nombre de Usuario</label>

            <input type="text" class="form-control" id="edit_usuario" name="edit_usuario" required>

          </div>

          <div class="mb-3">

            <label for="edit_rol" class="form-label">Rol</label>

            <select class="form-select" id="edit_rol" name="edit_rol" required>

                <option value="Tripulacion">Tripulación</option>

                <option value="Administrativo">Administrativo</option>

            </select>

          </div>

        </div>

        <div class="modal-footer">

          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

          <button type="submit" class="btn btn-primary">Guardar Cambios</button>

        </div>

      </form>

    </div>

  </div>

</div>



<!-- Modal para Cambiar contraseña -->

<div class="modal fade" id="cambiarClaveModal" tabindex="-1" aria-labelledby="cambiarClaveModalLabel" aria-hidden="true">

  <div class="modal-dialog">

    <div class="modal-content">

      <div class="modal-header">

        <h5 class="modal-title" id="cambiarClaveModalLabel">Cambiar contraseña para <span id="modalUserName"></span></h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

      </div>

      <form method="POST" action="gestion_usuarios.php">

          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

          <div class="modal-body">

                <input type="hidden" name="accion" value="cambiar_clave">

                <input type="hidden" name="id_usuario_clave" id="id_usuario_clave">

                <div class="mb-3">

                    <label for="nueva_clave" class="form-label">Nueva contraseña</label>

                    <input type="password" class="form-control" id="nueva_clave" name="nueva_clave" required>

                </div>

                <div class="mb-3">

                    <label for="confirmar_nueva_clave_modal" class="form-label">Confirmar Nueva contraseña</label>

                    <input type="password" class="form-control" id="confirmar_nueva_clave_modal" name="confirmar_nueva_clave" required>

                </div>

          </div>

          <div class="modal-footer">

            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

            <button type="submit" class="btn btn-primary">Guardar Cambios</button>

          </div>

      </form>

    </div>

  </div>

</div>



<script>

document.addEventListener('DOMContentLoaded', function () {

    // --- Logica para el modal de EDICIN de usuario ---

    var editarUsuarioModal = document.getElementById('editarUsuarioModal');

    editarUsuarioModal.addEventListener('show.bs.modal', function (event) {

        var button = event.relatedTarget;

        var usuario = JSON.parse(button.getAttribute('data-usuario'));



        document.getElementById('edit_id_usuario').value = usuario.id;

        document.getElementById('edit_nombres').value = usuario.nombres;

        document.getElementById('edit_apellidos').value = usuario.apellidos;

        document.getElementById('edit_id_cc').value = usuario.id_cc;

        document.getElementById('edit_id_registro').value = usuario.id_registro;

        document.getElementById('edit_usuario').value = usuario.usuario;

        document.getElementById('edit_rol').value = usuario.rol;

    });



    // --- Logica para el modal de CAMBIO DE contraseña ---

    var cambiarClaveModal = document.getElementById('cambiarClaveModal');

    cambiarClaveModal.addEventListener('show.bs.modal', function (event) {

        var button = event.relatedTarget;

        var userId = button.getAttribute('data-userid');

        var userName = button.getAttribute('data-username');



        var modalTitle = cambiarClaveModal.querySelector('.modal-title #modalUserName');

        var userIdInput = cambiarClaveModal.querySelector('#id_usuario_clave');



        modalTitle.textContent = userName;

        userIdInput.value = userId;

    });

});

</script>



<?php include __DIR__ . '/../footer.php'; ?>











