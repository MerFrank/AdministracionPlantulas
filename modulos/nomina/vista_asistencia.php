<?php
/**
 * Vista del reporte de asistencia
 */
?>
<!-- Tabla de Asistencia Resumen -->
<div class="form-container-nomina">
    <h2 class="section-title-nomina">Resumen de Asistencia</h2>
    <div class="table-responsive-nomina">
        <table class="table-nomina">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Total Horas</th>
                    <th>Turnos Completos</th>
                    <th>Turnos Simples</th>
                    <th>Registros Incompletos</th>
                    <th>Días Sin Registro</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horasPorEmpleado as $id => $horas): 
                    if (in_array($id, $empleadosExcluir)) continue;
                    
                    $clasif = $clasificacionEmpleados[$id] ?? [];
                    $totalDias = 5;
                    $diasTrabajados = $totalDias - ($clasif['dias_sin_registro'] ?? 0);
                    
                    // Determinar estado
                    if ($diasTrabajados == 0) {
                        $estado = "❌ Sin registros";
                        $color = "red";
                    } elseif (($clasif['registros_incompletos'] ?? 0) > 2) {
                        $estado = "⚠️ Registros incompletos";
                        $color = "orange";
                    } elseif (($clasif['turnos_completos'] ?? 0) >= 3) {
                        $estado = "✅ Turnos completos";
                        $color = "green";
                    } else {
                        $estado = "ℹ️ Patrón mixto";
                        $color = "blue";
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($id) ?></td>
                    <td><?= htmlspecialchars($empleados[$id] ?? 'Desconocido') ?></td>
                    <td><strong><?= number_format($horas, 2) ?></strong></td>
                    <td style="color: green;"><?= $clasif['turnos_completos'] ?? 0 ?></td>
                    <td style="color: blue;"><?= $clasif['turnos_simples'] ?? 0 ?></td>
                    <td style="color: orange;"><?= $clasif['registros_incompletos'] ?? 0 ?></td>
                    <td style="color: red;"><?= $clasif['dias_sin_registro'] ?? 0 ?></td>
                    <td style="color: <?= $color ?>;"><strong><?= $estado ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reporte Detallado de Asistencia por Persona -->
<div class="form-container-nomina">
    <h2 class="section-title-nomina">Reporte Detallado de Asistencia por Empleado</h2>
    <?php foreach ($detalleDias as $id => $dias): 
        if (in_array($id, $empleadosExcluir)) continue;
        
        $infoEmpleado = $nominaCompleta[$id] ?? null;
    ?>
    <div class="employee-detail-section">
        <h3 style="margin-top: 0; color: #333;">
            <?= htmlspecialchars($empleados[$id] ?? "Empleado $id") ?> (ID: <?= htmlspecialchars($id) ?>)
            <?php if ($infoEmpleado && !isset($infoEmpleado['error'])): ?>
            - <?= htmlspecialchars($infoEmpleado['puesto'] ?? 'Sin puesto') ?>
            <?php endif; ?>
        </h3>
        <div class="table-responsive-nomina">
            <table class="table-nomina">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Registros</th>
                        <th>Entrada Real</th>
                        <th>Salida Real</th>
                        <th>Horas Trabajadas</th>
                        <th>Tipo de Registro</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalHorasEmpleado = 0;
                    foreach ($dias as $dia => $info): 
                        $totalHorasEmpleado += $info['horas'];
                        $colorTipo = match($info['tipo']) {
                            'turno_completo' => 'green',
                            'turno_simple' => 'blue', 
                            'registro_incompleto' => 'orange',
                            'sin_registro' => 'red',
                            default => 'black'
                        };
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($dia) ?></strong></td>
                        <td><?= htmlspecialchars($info['registros']) ?></td>
                        <td><?= htmlspecialchars($info['entrada'] ?? '--:--') ?></td>
                        <td><?= htmlspecialchars($info['salida'] ?? '--:--') ?></td>
                        <td><?= number_format($info['horas'], 2) ?> h</td>
                        <td style="color: <?= $colorTipo ?>;">
                            <strong>
                                <?= match($info['tipo']) {
                                    'turno_completo' => '✅ Completo (4 registros)',
                                    'turno_simple' => '🔵 Simple (2 registros)',
                                    'registro_incompleto' => '⚠️ Incompleto',
                                    'sin_registro' => '❌ Sin registro',
                                    default => $info['tipo']
                                } ?>
                            </strong>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: #f9f9f9; font-weight: bold;">
                        <td colspan="4" style="text-align: right;">Total Semanal:</td>
                        <td><?= number_format($totalHorasEmpleado, 2) ?> h</td>
                        <td>
                            <?php 
                            $clasif = $clasificacionEmpleados[$id] ?? [];
                            echo "Completos: " . ($clasif['turnos_completos'] ?? 0) . " | ";
                            echo "Simples: " . ($clasif['turnos_simples'] ?? 0) . " | ";
                            echo "Incompletos: " . ($clasif['registros_incompletos'] ?? 0);
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>