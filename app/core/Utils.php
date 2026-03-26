<?php
/**
 * app/core/Utils.php
 * Funciones de ayuda genéricas y cálculos.
 */

class Utils {

    /**
     * Agarra un listado de asistencias y saca el porcentaje global.
     */
    public static function calcularAsistencia(int $asistencias, int $totales): ?float {
        if ($totales === 0) return null; // Sin clases
        return round(($asistencias / $totales) * 100, 1);
    }

    /**
     * Define el color de texto según el porcentaje.
     */
    public static function colorPct(?float $pct): string {
        if ($pct === null) return 'text-gray-400';
        if ($pct >= 85) return 'text-brand-green-dark';
        if ($pct >= 70) return 'text-amber-500';
        return 'text-red-500';
    }

    /**
     * Formateador de tiempo relativo ("Hace 5 minutos").
     */
    public static function tiempoRelativo(?string $fecha): string {
        if (!$fecha) return "Nunca";
        $dt = new DateTime($fecha);
        $diff = (new DateTime())->diff($dt);

        if ($diff->y > 0) return "Hace {$diff->y} año" . ($diff->y > 1 ? "s" : "");
        if ($diff->m > 0) return "Hace {$diff->m} mes" . ($diff->m > 1 ? "es" : "");
        if ($diff->d > 0) return "Hace {$diff->d} día" . ($diff->d > 1 ? "s" : "");
        if ($diff->h > 0) return "Hace {$diff->h} hr"  . ($diff->h > 1 ? "s" : "");
        if ($diff->i > 0) return "Hace {$diff->i} min" . ($diff->i > 1 ? "s" : "");
        
        return "Hace unos instantes";
    }

    /**
     * Recorte de texto seguro (fallback si no existe mbstring).
     */
    public static function safeSubstr(?string $str, int $start, int $length): string {
        if (!$str) return "";
        if (function_exists('mb_substr')) {
            return mb_substr($str, $start, $length);
        }
        return substr($str, $start, $length);
    }

    /**
     * Elimina acentos y tildes de una cadena.
     */
    public static function eliminarAcentos(?string $str): string {
        if (!$str) return "";
        $a = ['À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ'];
        $b = ['A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y'];
        return str_replace($a, $b, $str);
    }
}
