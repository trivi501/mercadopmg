<?php

namespace App\Services\Import;

/**
 * Agrupa los giros capturados libremente ("FRUTTAS VERDURAS", "TACOS DE CANASTA"…)
 * en el catálogo de giros del sistema. El texto original se conserva en
 * merchants.business_description.
 */
final class BusinessTypeClassifier
{
    public const string FALLBACK = 'Otros';

    /**
     * Giro del catálogo => palabras clave (texto normalizado, sin acentos).
     * Gana la palabra clave que aparece primero en el texto; en empate, la más larga.
     *
     * @var array<string, list<string>>
     */
    public const array RULES = [
        'Alimentos preparados' => [
            'ALIMENTO', 'COMIDA', 'TACO', 'GORDITA', 'BIRRIA', 'CARNITAS', 'FLAUTA', 'HAMBURGUESA', 'TORTA', 'TAMAL',
            'ELOTE', 'ESQUITE', 'ANTOJITO', 'MENUDO', 'POZOLE', 'BURRITO', 'TOSTADA', 'ENCHILADA', 'PIZZA', 'HOT DOG',
            'SOPE', 'QUESADILLA', 'CHURRO', 'NACHO', 'DORINACHO', 'MARISCO', 'CEVICHE', 'COCTEL', 'PAPAS', 'GUISO',
            'BARBACOA', 'PANCITA', 'MOLE', 'CHILAQUIL', 'CRUDITA', 'FRESAS CON CREMA', 'HOTCAKE', 'CREPA', 'WAFFLE', 'CAMARON',
        ],
        'Carnes y embutidos' => ['CARNE', 'CARNICERIA', 'POLLO', 'EMBUTIDO', 'CHORIZO', 'PESCADO', 'SALCHICHONERIA'],
        'Cremería y lácteos' => ['CREMERIA', 'QUESO', 'YOGURT', 'YOGUR', 'LACTEO', 'LECHE', 'CREMA'],
        'Frutas y verduras' => [
            'FRUTA', 'FRUTTA', 'VERDURA', 'LEGUMBRE', 'NOPAL', 'TUNA', 'CHILE', 'AJO', 'FRESA', 'AGUACATE', 'JITOMATE',
            'TOMATE', 'CEBOLLA', 'LIMON', 'NARANJA', 'PLATANO', 'MANZANA', 'MELON', 'SANDIA', 'MANGO', 'GUAYABA', 'HONGO',
        ],
        'Semillas y cereales' => ['SEMILLA', 'CEREAL', 'ESPECIA', 'ESPECIE', 'GRANEL', 'GRANO', 'FRIJOL', 'CACAHUATE', 'NUEZ', 'PEPITA', 'MIEL'],
        'Abarrotes' => ['ABARROTE', 'TORTILLA', 'PAN ', 'PANADERIA', 'GALLETA', 'GALLERTA', 'HUEVO', 'BIMBO', 'PANAL', 'BEBE'],
        'Dulces, postres y botanas' => ['DULCE', 'BOTANA', 'FRITURA', 'PALOMITA', 'ALGODON', 'CHICHARRON', 'GOLOSINA', 'CHAMOY', 'POSTRE', 'DONA', 'GELATINA', 'SHELO', 'PASTEL', 'FLAN'],
        'Bebidas y nieves' => ['JUGO', 'AGUA', 'REFRESCO', 'RASPADO', 'NIEVE', 'HELADO', 'PALETA', 'CAFE', 'BEBIDA', 'LICUADO', 'TEJUINO', 'ATOLE', 'CHAMPURRADO', 'TEPACHE'],
        'Plantas y flores' => ['PLANTA', 'FLOR', 'MACETA', 'VIVERO'],
        'Ropa' => ['ROPA', 'BONETERIA', 'LENCERIA', 'UNIFORME', 'PACA', 'BLUSA', 'PANTALON', 'CALCETA', 'CALCETIN', 'TEXTIL'],
        'Calzado' => ['TENIS', 'CALZADO', 'ZAPATO', 'HUARACHE', 'BOTA', 'SANDALIA'],
        'Herramientas' => ['HERRAMIENTA', 'FERRETERIA', 'TORNILLO', 'REFACCION'],
        'Electrónica' => ['ELECTRONIC', 'CELULAR', 'AUDIFONO', 'CABLE', 'CARGADOR', 'BOCINA'],
        'Juguetes' => ['JUGUETE', 'GLOBO', 'FIGURA', 'CARRITO', 'CARROS DE COLECCION'],
        'Accesorios' => ['ACCESORIO', 'ACSESORIO', 'BISUTERIA', 'LENTE', 'BOLSA', 'RELOJ', 'GORRA', 'CINTO', 'CARTERA', 'MOCHILA', 'AGUJETA', 'MICA', 'FUNDA'],
        'Artesanías' => ['ARTESAN', 'YESO', 'BARRO', 'ALCANCIA', 'JICARITA', 'MADERA', 'TALAVERA'],
        'Cosméticos' => ['COSMETIC', 'PERFUM', 'MAQUILLAJE', 'CREMAS'],
        'Hogar y novedades' => ['BETTERWARE', 'PLASTICO', 'HOGAR', 'COCINA', 'NOVEDAD', 'BAZAR', 'LIBRO', 'INCIENSO', 'PAPELERIA', 'BLANCOS', 'COLCHA', 'COBIJA', 'JARCERIA', 'LIMPIEZA', 'MERCERIA', 'COSTURA'],
        'Productos naturistas' => ['NATURISTA', 'HIERBA', 'HERBOLARIA'],
        'Servicios' => ['BOLERIA', 'BOLERO', 'CERRAJERIA', 'LLAVE', 'CANDADO', 'REPARACION', 'AFILADOR'],
        'Varios' => ['VARIOS', 'VARIO', 'VARIAS', 'CHACHARA'],
    ];

    /**
     * Giros del catálogo que el importador agrega si no existen.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        return [...array_keys(self::RULES), self::FALLBACK];
    }

    public static function classify(?string $raw): string
    {
        $text = ' '.PadronText::normalize($raw).' ';
        $best = null;

        foreach (self::RULES as $type => $keywords) {
            foreach ($keywords as $keyword) {
                // Inicio de palabra: "AJO" no coincide dentro de "TRABAJO".
                if (! preg_match('/\b'.preg_quote(trim($keyword), '/').(str_ends_with($keyword, ' ') ? '\b' : '').'/', $text, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                $pos = $m[0][1];
                if ($best === null || $pos < $best['pos'] || ($pos === $best['pos'] && strlen($keyword) > $best['len'])) {
                    $best = ['type' => $type, 'pos' => $pos, 'len' => strlen($keyword)];
                }
            }
        }

        return $best['type'] ?? self::FALLBACK;
    }
}
