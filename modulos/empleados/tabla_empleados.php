<table class="table table-striped table-hover mt-3">
    <thead>
        <tr>
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
        <?php if (empty($empleados)): ?>
            <tr>
                <td colspan="11" class="text-center">No se encontraron empleados</td>
            </tr>
        <?php else: ?>
            <?php foreach ($empleados as $empleado): ?>
                <tr>
                    <td><?= htmlspecialchars($empleado['id_empleado'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($empleado['nombre'] ?? 'N/A') ?></td>
                    <td>
                        <?= htmlspecialchars($empleado['apellido_paterno'] ?? '') ?>
                        <?= !empty($empleado['apellido_materno']) ? ' ' . htmlspecialchars($empleado['apellido_materno']) : '' ?>
                    </td>
                    <td><?= htmlspecialchars($empleado['telefono'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($empleado['email'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($empleado['curp'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($empleado['rfc'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($empleado['nss'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($empleado['fecha_contratacion'] ?? 'N/A') ?></td>
                    <td><?= ($empleado['activo'] == 1) ? 'Activo' : 'Inactivo' ?></td>
                    <td>
                        <div class="btn-group" role="group">
                            <a href="editar_empleado.php?id_empleado=<?= $empleado['id_empleado'] ?>" 
                               class="btn btn-sm btn-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="eliminar_empleado.php?id_empleado=<?= $empleado['id_empleado'] ?>" 
                               class="btn btn-sm btn-danger" title="Eliminar"
                               onclick="return confirm('¿Estás seguro de eliminar este empleado?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>