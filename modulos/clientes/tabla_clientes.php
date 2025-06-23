<div class="table-responsive">
    <table class="table table-striped table-hover" style="min-width: 100%;">
        <thead class="sticky-top">
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 10%;">Alias</th>
                <th style="width: 15%;">Nombre/Razón Social</th>
                <th style="width: 15%;">Empresa</th>
                <th style="width: 15%;">Contacto</th>
                <th style="width: 10%;">Teléfono</th>
                <th style="width: 15%;">Email</th>
                <th style="width: 15%;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clientes)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="bi bi-people text-muted" style="font-size: 2rem;"></i>
                        <p class="mt-2">No se encontraron clientes <?= isset($_GET['busqueda']) && !empty($_GET['busqueda']) ? 'con el criterio de búsqueda' : '' ?></p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($cliente['id_cliente']) ?></span></td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 150px;" title="<?= !empty($cliente['alias']) ? htmlspecialchars($cliente['alias']) : 'N/A' ?>">
                            <?= !empty($cliente['alias']) ? htmlspecialchars($cliente['alias']) : '<span class="text-muted">N/A</span>' ?>
                        </span>
                    </td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cliente['nombre_Cliente']) ?>">
                            <?= htmlspecialchars($cliente['nombre_Cliente']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= !empty($cliente['nombre_Empresa']) ? htmlspecialchars($cliente['nombre_Empresa']) : 'N/A' ?>">
                            <?= !empty($cliente['nombre_Empresa']) ? htmlspecialchars($cliente['nombre_Empresa']) : '<span class="text-muted">N/A</span>' ?>
                        </span>
                    </td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cliente['nombre_contacto']) ?>">
                            <?= htmlspecialchars($cliente['nombre_contacto']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cliente['email']) ?>">
                            <?= htmlspecialchars($cliente['email']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="editar_cliente.php?id=<?= $cliente['id_cliente'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="eliminar_cliente.php?id=<?= $cliente['id_cliente'] ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Estás seguro de que deseas eliminar este cliente?');">
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