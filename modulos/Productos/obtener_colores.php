<?php
require_once __DIR__ . '/../../includes/config.php';
$db = new Database();
$conexion = $db->conectar();

header('Content-Type: text/html; charset=utf-8');

$especieId = $_GET['especie'] ?? null;

if (!$especieId) {
    echo '<div class="empty-message">No se especificó una especie</div>';
    exit;
}

try {
    $sql = "SELECT c.id_color, c.nombre_color 
            FROM colores c 
            WHERE c.id_especie = :id_especie 
            ORDER BY c.nombre_color";
    $stmt = $conexion->prepare($sql);
    $stmt->bindParam(':id_especie', $especieId, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<span class='color-badge'>
                    {$row['nombre_color']}
                    <span class='color-actions'>
                        <a href='Registro_colores.php?editar={$row['id_color']}' class='btn btn-sm btn-warning'>Editar</a>
                        <a href='#' onclick='eliminarColor({$row['id_color']})' class='btn btn-sm btn-danger'>Eliminar</a>
                    </span>
                  </span>";
        }
    } else {
        echo '<div class="empty-message">Esta especie no tiene colores asignados</div>';
    }
} catch(PDOException $e) {
    echo '<div class="empty-message">Error al cargar colores</div>';
}
?>