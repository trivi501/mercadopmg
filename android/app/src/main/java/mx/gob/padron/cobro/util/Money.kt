package mx.gob.padron.cobro.util

import java.math.BigDecimal
import java.math.RoundingMode
import java.text.NumberFormat
import java.text.Normalizer
import java.util.Locale

/**
 * Importes con BigDecimal (nunca Double). Mismas reglas que el servidor:
 * boletos = cantidad × precio; total = suma de renglones.
 */
object Money {
    private val mxLocale = Locale.Builder().setLanguage("es").setRegion("MX").build()

    fun parse(value: String?): BigDecimal =
        value?.toBigDecimalOrNull()?.setScale(2, RoundingMode.HALF_UP) ?: BigDecimal.ZERO.setScale(2)

    fun line(quantity: Int, unitPrice: String): BigDecimal =
        parse(unitPrice).multiply(BigDecimal(quantity)).setScale(2, RoundingMode.HALF_UP)

    fun sum(values: Iterable<BigDecimal>): BigDecimal =
        values.fold(BigDecimal.ZERO.setScale(2)) { acc, v -> acc.add(v) }

    /** "$1,234.50" */
    fun format(value: BigDecimal): String {
        val f = NumberFormat.getCurrencyInstance(mxLocale)
        f.minimumFractionDigits = 2
        f.maximumFractionDigits = 2
        return f.format(value)
    }

    fun format(value: String?): String = format(parse(value))

    /** Texto para el servidor: "200.00" */
    fun plain(value: BigDecimal): String = value.setScale(2, RoundingMode.HALF_UP).toPlainString()
}

/** Minúsculas y sin acentos, para búsquedas locales. */
fun String.normalizedForSearch(): String =
    Normalizer.normalize(lowercase(Locale.ROOT), Normalizer.Form.NFD)
        .replace(Regex("\\p{Mn}+"), "")
