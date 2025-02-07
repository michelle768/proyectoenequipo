<?php

$tarifa_por_hora = 2.00;

$horas_totales = 4 + (30 / 60); 

$costo_total = $tarifa_por_hora * $horas_totales;

echo "El cliente debe pagar $". number_format($costo_total, 2) ." por estacionar su vehículo durante 4 horas y 30 minutos en el centro comercial Maya.";
?>