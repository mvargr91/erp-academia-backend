<?php

if (!function_exists('format_messages_validator')) {
    /**
     * Formatea los mensajes devueltos el validador de una petición
     *
     * @param $validator
     * Validador
     *
     * @return array
     * Lista de mensajes
     */
    function format_messages_validator($validator){
        $messages = [];
        $errorsAttributes = $validator->messages();
        if(isset($errorsAttributes)){
            foreach ($errorsAttributes->messages() as $errorsAttribute){
                foreach ($errorsAttribute as $error){
                    array_push($messages, $error);
                }
            }
        }

        return $messages;
    }
}

if (!function_exists('get_response_body')) {
    /**
     * Formatea el cuerpo de la respuesta para una petición http
     *
     * @param  array|mixed  $messages
     * Mensajes de respuesta
     *
     * @param null $data
     * Datos de respuesta
     *
     * @return array Lista de mensajes
     * Retorna cuerpo para respuesta http
     */
    function get_response_body($messages, $data = null){
        $response = [];
        if(isset($data)){
            $response['datos'] = $data;
        }
        $response['mensajes'] = is_array($messages) ? $messages : [$messages];
        return $response;
    }
}

if (!function_exists('format_order_by_attributes')) {
    /**
     * Formatea los atributos de ordenamiento de una colección
     *
     * @param $data
     * Atributos de ordenamiento
     *
     * @return array
     * Lista de atributos para ordenar
     */
    function format_order_by_attributes($data){
        $orderBys = [];
        $orderBysAux = explode(",", $data['ordenar_por']);
        foreach ($orderBysAux as $orderByExplode){
            $orderByAux = explode(":", $orderByExplode);
            $orderBys[$orderByAux[0]] = $orderByAux[1];
        }

        return $orderBys;
    }
}

if (!function_exists('convertir_a_numero')) {
    function convertir_a_numero($valor)
    {
        // Eliminar los espacios en blanco
        $valor = trim($valor);

        // Verificar si el valor tiene paréntesis
        if (preg_match('/^\((.*)\)$/', $valor, $matches)) {
            // Si tiene paréntesis, convertir el valor interno a negativo
            $numero = $matches[1];

            // Reemplazar los puntos y las comas para convertirlo a un formato numérico
            $numero = str_replace(['.', ','], ['', '.'], $numero);

            // Convertir a negativo
            return -1 * floatval($numero);
        } else {
            // Si no tiene paréntesis, solo convertir el valor
            $numero = str_replace(['.', ','], ['', '.'], $valor);
            return floatval($numero);
        }
    }
}

if (!function_exists('limpiarValor')) {
    function limpiarValor($valor) {
        // Limpiar el valor de saltos de línea y espacios innecesarios
        return strpos($valor, "\n") !== false ? explode("\n", $valor)[0] : trim($valor);
    }

}

if (!function_exists('formatear_valor_con_miles')) {
    
    /**
     * Convierte un número de formato punto-decimal a coma-decimal y aplica formato de miles.
     *
     * @param string $valor El valor numérico como cadena.
     * @return string El valor formateado con separadores de miles y coma como decimal.
     */
    function formatear_valor_con_miles($valor)
    {
        // Convertir el valor a float para asegurar el manejo correcto del número
        $numero = floatval($valor);

        // Obtener la cantidad de decimales del valor original
        $decimales = strlen(substr(strrchr($valor, "."), 1));

        // Formatear el número con separadores de miles (.) y decimales con coma (,)
        // number_format acepta el número, la cantidad de decimales, el separador de decimales y el de miles.
        return number_format($numero, $decimales, ',', '.');
    }


}

if (!function_exists('convertir_a_numero_decimal')) {
    /**
     * Convierte un valor de cadena con punto decimal a un número float.
     *
     * @param string $valor El valor numérico como cadena.
     * @return float El valor convertido a número.
     */
    function convertir_a_numero_decimal($valor)
    {
        // Reemplazar la coma por un punto para manejar los decimales correctamente
        $valor = str_replace(',', '.', $valor);
        
        // Convertir a float y devolver
        return floatval($valor);
    }
}


if (!function_exists('eliminarLetras')) {
    function eliminarLetras($valor)
    {
        // Usar una expresión regular para mantener solo números, comas y puntos
        return preg_replace('/[^0-9,]/', '', $valor);
    }
}


if (!function_exists('eliminarPuntos')) {
    /**
     * Elimina los puntos de una cadena si existen, dejando intacto el valor si no hay puntos.
     *
     * @param string $valor La cadena original.
     * @return string La cadena sin puntos.
     */
    function eliminarPuntos($valor)
    {
         // Eliminar todos los puntos de la cadena
        $valorSinPuntos = str_replace('.', '', $valor);
        // Reemplazar la coma decimal por un punto para que sea interpretable como número
        $valorSinPuntosYComa = str_replace(',', '.', $valorSinPuntos);
        // Convertir a número flotante
        return floatval($valorSinPuntosYComa);
    }
}