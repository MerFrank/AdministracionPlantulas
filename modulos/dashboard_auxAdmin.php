<?php
require_once(__DIR__ . '/../includes/validacion_session.php');
require_once(__DIR__ . '/../includes/config.php');
// Configuración de la página
$titulo = "Panel de Control Principal";
$encabezado = "Sistema de Gestión Plantulas";
$subtitulo = "Seleccione el módulo que desea administrar";
$active_page = "dashboard";
$ruta = "../session/logout.php";
$texto_boton = "Cerra Sessión";
//Incluir el header
require_once(__DIR__ . '/../includes/header.php');
?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><?php echo htmlspecialchars($encabezado); ?></h1>
            <div class="user-info">
                <span class="me-2"><?php echo htmlspecialchars($_SESSION['Nombre'] ?? 'Usuario'); ?></span>
                <i class="bi bi-person-circle"></i>
            </div>
        </div>
        
        <p class="lead"><?php echo htmlspecialchars($subtitulo); ?></p>
        
        <div class="row g-4">
                       
            <!-- Módulo de Cuentas -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-wallet2 text-success fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Cuentas</h3>
                        </div>
                        <p class="card-text">Gestión de cuentas bancarias y transacciones.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/Cuentas/dashboard_cuentas.php"
                         class="btn btn-outline-success stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Módulo de Egresos -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-cash-coin text-danger fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Egresos</h3>
                        </div>
                        <p class="card-text">Registro y control de gastos y egresos.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/Egresos/dashboard_egresos.php"
                         class="btn btn-outline-danger stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Módulo de Proveedores -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-truck text-warning fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Proveedores</h3>
                        </div>
                        <p class="card-text">Administración de proveedores y compras.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/proveedores/dashboard_proveedores.php"
                         class="btn btn-outline-warning stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Módulo de Sucursales -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-shop text-info fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Sucursales</h3>
                        </div>
                        <p class="card-text">Gestión de sucursales y locales comerciales.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/Sucursales/dashboard_sucursales.php" 
                        class="btn btn-outline-info stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer incluido desde footer.php -->
    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>

    <!-- JavaScript -->
    <!-- <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>