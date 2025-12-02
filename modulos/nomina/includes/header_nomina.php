<?php
/**
 * Header específico para la nómina con estilos
 */
?>
<style>
/* ESTILOS ESPECÍFICOS PARA NÓMINA - SOBRESCRIBIENDO REGLAS EXISTENTES */
.form-container-nomina {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    margin-bottom: 25px;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box;
}

/* FORZAR ANCHO COMPLETO PARA TODOS LOS ELEMENTOS DE NÓMINA */
.container-nomina-full {
    width: 100% !important;
    max-width: 100% !important;
    padding: 0 15px;
    margin: 0 auto;
}

.table-responsive-nomina {
    overflow-x: auto;
    width: 100% !important;
    margin-top: 20px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

.table-nomina {
    width: 100% !important;
    min-width: 1400px !important;
    border-collapse: collapse;
    background-color: white;
    margin-bottom: 0;
}

.table-nomina th,
.table-nomina td {
    border: 1px solid #dee2e6;
    padding: 12px;
    text-align: center;
    vertical-align: middle;
}

.table-nomina thead {
    background-color: #45814d !important;
    color: white;
}

.table-nomina thead th {
    background-color: #45814d !important;
    color: white !important;
    text-transform: uppercase;
    font-weight: 500;
    padding: 1rem;
    border: none;
}

.form-group-nomina {
    margin-bottom: 20px;
    width: 100% !important;
}

.form-group-nomina label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
    width: 100% !important;
}

.form-group-nomina input[type="file"] {
    width: 100% !important;
    padding: 10px;
    border: 2px dashed #ced4da;
    border-radius: 5px;
    background: white;
    transition: all 0.3s ease;
}

.btn-submit-nomina {
    background: #007bff;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s ease;
    width: auto !important;
    display: inline-block !important;
}

.btn-submit-nomina:hover {
    background: #0056b3;
}

.total-row {
    background: #e3f2fd !important;
    font-weight: bold;
    font-size: 1.1em;
}

.total-row td {
    padding: 15px 12px;
    border-top: 2px solid #007bff;
}

.actividades-container {
    max-height: 150px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px;
    background: white;
    width: 100% !important;
}

.actividades-item {
    margin-bottom: 8px;
    padding: 5px;
    border-radius: 3px;
    transition: background 0.2s ease;
    width: 100% !important;
}

.actividades-item:hover {
    background: #f8f9fa;
}

.actividades-item label {
    font-weight: normal;
    margin-bottom: 0;
    cursor: pointer;
    width: 100% !important;
}

.positive-amount {
    color: #28a745;
    font-weight: 600;
}

.negative-amount {
    color: #dc3545;
    font-weight: 600;
}

.section-title-nomina {
    color: #495057;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
    margin-bottom: 20px;
    width: 100% !important;
}

.employee-detail-section {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    width: 100% !important;
}

/* Estilos para condonación */
.condonar-checkbox {
    transform: scale(1.2);
    margin: 0 8px;
}

.condonar-label {
    font-weight: normal;
    cursor: pointer;
    font-size: 12px;
}

.descuento-condonado {
    text-decoration: line-through;
    color: #6c757d !important;
}

.sin-descuento {
    color: #28a745 !important;
    font-weight: bold;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .container-nomina-full {
        padding: 0 10px;
    }

    .form-container-nomina {
        padding: 15px;
    }

    .table-nomina {
        min-width: 1200px !important;
    }
}

@media (max-width: 576px) {
    .container-nomina-full {
        padding: 0 5px;
    }

    .form-container-nomina {
        padding: 10px;
    }

    .btn-submit-nomina {
        width: 100% !important;
        padding: 15px;
    }
}
</style>
