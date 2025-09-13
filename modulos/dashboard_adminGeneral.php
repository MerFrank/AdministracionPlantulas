<?php

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
                       
            <div class="row g-4">
            <!-- Módulo de Usuarios -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-shield-lock text-primary fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Usuarios</h3>
                        </div>
                        <p class="card-text">Gestión de usuarios, roles y permisos del sistema.</p>
                        <a href="<?php echo BASE_URL; ?>/usuarios/panel_usuarios.php" 
                        class="btn btn-outline-primary stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Módulo de Clientes -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-people-fill text-success fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Clientes</h3>
                        </div>
                        <p class="card-text">Administración de clientes y su información.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/clientes/dashboard_clientes.php" 
                        class="btn btn-outline-success stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Módulo de Productos -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-box-seam text-info fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Productos</h3>
                        </div>
                        <p class="card-text">Gestión de inventario y catálogo de productos.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/Productos/dashboard_registroProducto.php" 
                        class="btn btn-outline-info stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Módulo de Cotizaciones -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-dark bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-clipboard2-plus text-dark fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Cotizaciones</h3>
                        </div>
                        <p class="card-text">Crea y gestiona cotizaciones.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/cotizacion/dashboard_cotizaciones.php"
                         class="btn btn-outline-dark stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Módulo de Ventas -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-currency-dollar text-warning fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Ventas</h3>
                        </div>
                        <p class="card-text">Gestión de transacciones y facturación.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/ventas/dashboard_ventas.php" 
                        class="btn btn-outline-warning stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Módulo de Empleados -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-person-badge text-danger fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Empleados</h3>
                        </div>
                        <p class="card-text">Administración del personal y recursos humanos.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/empleados/dashboard_empleados.php"
                         class="btn btn-outline-danger stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Módulo de Puestos -->
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-info bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-person-lines-fill text-info fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Puestos</h3>
                        </div>
                        <p class="card-text">Gestiona los puestos de los empleados.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/puestos/dashboard_puestos.php" 
                        class="btn btn-outline-info stretched-link">
                            Acceder <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>


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
                            <div class="bg-dark bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-cash-coin text-dark fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Egresos</h3>
                        </div>
                        <p class="card-text">Registro y control de gastos y egresos.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/Egresos/dashboard_egresos.php" 
                        class="btn btn-outline-dark stretched-link">
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
                            <div class="bg-danger bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-truck text-danger fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Proveedores</h3>
                        </div>
                        <p class="card-text">Administración de proveedores y compras.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/proveedores/dashboard_proveedores.php"
                         class="btn btn-outline-danger stretched-link">
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
                            <div class="bg-warning bg-opacity-10 p-3 rounded me-3">
                                <i class="bi bi-shop text-warning fs-2"></i>
                            </div>
                            <h3 class="h5 mb-0">Sucursales</h3>
                        </div>
                        <p class="card-text">Gestión de sucursales y locales comerciales.</p>
                        <a href="<?php echo BASE_URL; ?>/modulos/Sucursales/dashboard_sucursales.php" 
                        class="btn btn-outline-warning stretched-link">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>