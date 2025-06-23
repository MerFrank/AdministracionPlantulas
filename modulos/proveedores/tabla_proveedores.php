<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Alias</th>
                <th>Nombre/Razón Social</th>
                <th>Empresa</th>
                <th>Contacto</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($proveedores)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="bi bi-search" style="font-size: 2rem;"></i>
                        <p class="mt-2">No se encontraron proveedores <?= !empty($busqueda) ? 'con ese criterio de búsqueda' : '' ?></p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($proveedores as $proveedor): ?>
                <tr>
                    <td><?= $proveedor['id_proveedor'] ?></td>
                    <td><?= !empty($proveedor['alias']) ? htmlspecialchars($proveedor['alias']) : '<span class="text-muted">N/A</span>' ?></td>
                    <td><?= htmlspecialchars($proveedor['nombre_proveedor']) ?></td>
                    <td><?= !empty($proveedor['nombre_empresa']) ? htmlspecialchars($proveedor['nombre_empresa']) : '<span class="text-muted">N/A</span>' ?></td>
                    <td><?= htmlspecialchars($proveedor['nombre_contacto']) ?></td>
                    <td><?= htmlspecialchars($proveedor['telefono']) ?></td>
                    <td><?= htmlspecialchars($proveedor['email']) ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="editar_proveedor.php?id=<?= $proveedor['id_proveedor'] ?>" class="btn btn-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="eliminar_proveedor.php?id=<?= $proveedor['id_proveedor'] ?>" class="btn btn-danger" title="Eliminar" 
                               onclick="return confirm('¿Estás seguro de eliminar este proveedor?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>