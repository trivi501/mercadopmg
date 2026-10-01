package mx.gob.padron.cobro

import mx.gob.padron.cobro.util.Money
import mx.gob.padron.cobro.util.normalizedForSearch
import org.junit.Assert.assertEquals
import org.junit.Test

class MoneyTest {

    @Test
    fun `cinco boletos por 40 pesos son 200`() {
        assertEquals("200.00", Money.plain(Money.line(5, "40.00")))
    }

    @Test
    fun `boletos mas anualidad son 355`() {
        val total = Money.sum(listOf(Money.line(5, "40.00"), Money.parse("155.00")))
        assertEquals("355.00", Money.plain(total))
    }

    @Test
    fun `la aritmetica decimal es exacta`() {
        assertEquals("0.30", Money.plain(Money.line(3, "0.10")))
    }

    @Test
    fun `importes invalidos se tratan como cero`() {
        assertEquals("0.00", Money.plain(Money.parse(null)))
        assertEquals("0.00", Money.plain(Money.parse("abc")))
    }

    @Test
    fun `busqueda local ignora acentos y mayusculas`() {
        assertEquals("juan perez lopez", "Juan Pérez López".normalizedForSearch())
    }
}
