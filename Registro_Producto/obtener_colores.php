<?php
include __DIR__ . '/../db/config.php';
$db = new Database();
$conexion = $db->conectar();

header('Content-Type: text/html; charset=utf-8');

$especieSeleccionada = $_GET['especie'] ?? null;

if ($especieSeleccionada) {
    try {
        $sql = "SELECT c.nombre_color 
                FROM Colores c 
                WHERE c.id_especie = :id_especie 
                ORDER BY c.nombre_color";
        $stmt = $conexion->prepare($sql);
        $stmt->bindParam(':id_especie', $especieSeleccionada, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<span class='color-badge'>{$row['nombre_color']}</span>";
            }
        } else {
            echo '<div class="empty-message">Esta especie no tiene colores asignados</div>';
        }
    } catch(PDOException $e) {
        echo '<div class="empty-message">Error al cargar colores</div>';
    }
} else {
    echo '<div class="empty-message">Seleccione una especie para ver sus colores</div>';
}
?>