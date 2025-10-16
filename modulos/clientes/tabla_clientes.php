<?php
require_once(__DIR__ . '/../../includes/validacion_session.php');?>

<div class="table-responsive">
    <!-- Tabla con estilos de rayado y hover -->
    <table class="table table-striped table-hover" style="min-width: 100%;">
        <!-- Encabezado de tabla que se mantiene fijo al hacer scroll -->
        <thead class="sticky-top">
            <tr>
                <!-- Columnas del encabezado con anchos específicos -->
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
        
        <!-- Cuerpo de la tabla -->
        <tbody>
            <?php if (empty($clientes)): ?>
                <!-- Mensaje cuando no hay clientes -->
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <!-- Icono de personas -->
                        <i class="bi bi-people text-muted" style="font-size: 2rem;"></i>
                        <!-- Mensaje condicional (si hay búsqueda o no) -->
                        <p class="mt-2">No se encontraron clientes <?= isset($_GET['busqueda']) && !empty($_GET['busqueda']) ? 'con el criterio de búsqueda' : '' ?></p>
                    </td>
                </tr>
            <?php else: ?>
                <!-- Loop para cada cliente -->
                <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <!-- Celda de ID con badge -->
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($cliente['id_cliente']) ?></span></td>
                    
                    <!-- Celda de Alias con truncado y tooltip -->
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 150px;" title="<?= !empty($cliente['alias']) ? htmlspecialchars($cliente['alias']) : 'N/A' ?>">
                            <?= !empty($cliente['alias']) ? htmlspecialchars($cliente['alias']) : '<span class="text-muted">N/A</span>' ?>
                        </span>
                    </td>
                    
                    <!-- Celda de Nombre/Razón Social con truncado -->
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cliente['nombre_Cliente']) ?>">
                            <?= htmlspecialchars($cliente['nombre_Cliente']) ?>
                        </span>
                    </td>
                    
                    <!-- Celda de Empresa con truncado -->
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= !empty($cliente['nombre_Empresa']) ? htmlspecialchars($cliente['nombre_Empresa']) : 'N/A' ?>">
                            <?= !empty($cliente['nombre_Empresa']) ? htmlspecialchars($cliente['nombre_Empresa']) : '<span class="text-muted">N/A</span>' ?>
                        </span>
                    </td>
                    
                    <!-- Celda de Contacto con truncado -->
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cliente['nombre_contacto']) ?>">
                            <?= htmlspecialchars($cliente['nombre_contacto']) ?>
                        </span>
                    </td>
                    
                    <!-- Celda de Teléfono -->
                    <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                    
                    <!-- Celda de Email con truncado -->
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cliente['email']) ?>">
                            <?= htmlspecialchars($cliente['email']) ?>
                        </span>
                    </td>
                    
                    <!-- Celda de Acciones con botones -->
                    <td>
                        <!-- Contenedor flex para los botones -->
                        <div class="d-flex gap-2">
                            <!-- Botón Editar (amarillo) -->
                            <a href="editar_cliente.php?id=<?= $cliente['id_cliente'] ?>" 
                               class="btn btn-sm text-white" 
                               style="background-color: var(--color-accent); border-color: var(--color-accent);"
                               title="Editar cliente">
                               <i class="bi bi-pencil"></i>
                            </a>
                            
                            <!-- Botón Eliminar (rojo) con confirmación -->
                            <a href="eliminar_cliente.php?id=<?= $cliente['id_cliente'] ?>" 
                               class="btn btn-sm text-white" 
                               style="background-color: var(--color-danger); border-color: var(--color-danger);"
                               title="Eliminar cliente"
                               onclick="return confirm('¿Estás seguro de eliminar este cliente?');">
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