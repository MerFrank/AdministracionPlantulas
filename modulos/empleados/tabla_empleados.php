<?php require_once(__DIR__ . '/../../includes/validacion_session.php');?>
<table class="table table-striped table-hover mt-3">
    <thead>
        <tr>
            <!-- Encabezados de columna -->
            <th>ID</th>
            <th>Nombre</th>
            <th>Apellidos</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>CURP</th>
            <th>RFC</th>
            <th>NSS</th>
            <th>Fecha Contratación</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <!-- Verifica si la lista de empleados está vacía -->
        <?php if (empty($empleados)): ?>
            <tr>
                <!-- Si no hay empleados, muestra un mensaje centrado -->
                <td colspan="11" class="text-center">No se encontraron empleados</td>
            </tr>
        <?php else: ?>
            <!-- Itera sobre cada empleado y muestra sus datos en una fila -->
            <?php foreach ($empleados as $empleado): ?>
                <tr>
                    <!-- Muestra el ID del empleado -->
                    <td><?= htmlspecialchars($empleado['id_empleado'] ?? 'N/A') ?></td>

                    <!-- Muestra el nombre del empleado -->
                    <td><?= htmlspecialchars($empleado['nombre'] ?? 'N/A') ?></td>

                    <!-- Muestra los apellidos (paterno y materno, si existe) -->
                    <td>
                        <?= htmlspecialchars($empleado['apellido_paterno'] ?? '') ?>
                        <?= !empty($empleado['apellido_materno']) ? ' ' . htmlspecialchars($empleado['apellido_materno']) : '' ?>
                    </td>

                    <!-- Muestra el teléfono del empleado -->
                    <td><?= htmlspecialchars($empleado['telefono'] ?? 'N/A') ?></td>

                    <!-- Muestra el email del empleado -->
                    <td><?= htmlspecialchars($empleado['email'] ?? 'N/A') ?></td>

                    <!-- Muestra el CURP del empleado -->
                    <td><?= htmlspecialchars($empleado['curp'] ?? 'N/A') ?></td>

                    <!-- Muestra el RFC del empleado -->
                    <td><?= htmlspecialchars($empleado['rfc'] ?? 'N/A') ?></td>

                    <!-- Muestra el NSS del empleado -->
                    <td><?= htmlspecialchars($empleado['nss'] ?? 'N/A') ?></td>

                    <!-- Muestra la fecha de contratación -->
                    <td><?= htmlspecialchars($empleado['fecha_contratacion'] ?? 'N/A') ?></td>

                    <!-- Muestra el estado (Activo o Inactivo) -->
                    <td><?= ($empleado['activo'] == 1) ? 'Activo' : 'Inactivo' ?></td>

                    <!-- Columna para los botones de acción -->
                    <td>
                        <!-- Contenedor flex para botones con espacio entre ellos -->
                        <div class="d-flex gap-2">
                            <!-- Botón para editar al empleado -->
                            
                                <a href="editar_empleado.php?id_empleado=<?= $empleado['id_empleado'] ?>" 
                                   class="btn btn-sm btn-primary" 
                                   style="background-color: var(--color-accent); border-color: var(--color-accent);"
                                   title="Editar">
                                    <i class="bi bi-pencil"></i> <!-- Ícono de lápiz -->
                                </a>

                                <!-- Botón para eliminar al empleado, con confirmación -->
                                <a href="eliminar_empleado.php?id_empleado=<?= $empleado['id_empleado'] ?>" 
                                   class="btn btn-sm btn-primary" 
                                   style="background-color: var(--color-danger); border-color: var(--color-danger);" 
                                   title="Eliminar"
                                   onclick="return confirm('¿Estás seguro de eliminar este empleado?')">
                                    <i class="bi bi-trash"></i> <!-- Ícono de basura -->
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
