<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora JavaScript</title>
    <script>
        // Funciones para realizar las operaciones matemáticas
        function sumar() {
            var num1 = parseFloat(document.getElementById("num1").value);
            var num2 = parseFloat(document.getElementById("num2").value);
            var resultado = num1 + num2;
            document.getElementById("resultado").innerText = "Resultado: " + resultado;
        }

        function restar() {
            var num1 = parseFloat(document.getElementById("num1").value);
            var num2 = parseFloat(document.getElementById("num2").value);
            var resultado = num1 - num2;
            document.getElementById("resultado").innerText = "Resultado: " + resultado;
        }

        function multiplicar() {
            var num1 = parseFloat(document.getElementById("num1").value);
            var num2 = parseFloat(document.getElementById("num2").value);
            var resultado = num1 * num2;
            document.getElementById("resultado").innerText = "Resultado: " + resultado;
        }

        function dividir() {
            var num1 = parseFloat(document.getElementById("num1").value);
            var num2 = parseFloat(document.getElementById("num2").value);
            if (num2 === 0) {
                document.getElementById("resultado").innerText = "Error: No se puede dividir por 0.";
            } else {
                var resultado = num1 / num2;
                document.getElementById("resultado").innerText = "Resultado: " +
