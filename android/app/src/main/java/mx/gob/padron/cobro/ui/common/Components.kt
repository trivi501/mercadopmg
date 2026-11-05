package mx.gob.padron.cobro.ui.common

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudOff
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.rememberTextMeasurer
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.TextUnit
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import mx.gob.padron.cobro.R
import mx.gob.padron.cobro.data.remote.TicketLine
import mx.gob.padron.cobro.ui.theme.PaidGreen
import mx.gob.padron.cobro.ui.theme.PendingAmber

@Composable
fun Loading(modifier: Modifier = Modifier) {
    Box(modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
}

@Composable
fun ErrorMessage(message: String, onRetry: (() -> Unit)? = null, modifier: Modifier = Modifier) {
    Column(
        modifier.fillMaxSize().padding(24.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text(message, textAlign = TextAlign.Center, style = MaterialTheme.typography.bodyLarge)
        if (onRetry != null) {
            Button(onClick = onRetry, modifier = Modifier.padding(top = 16.dp)) { Text("Reintentar") }
        }
    }
}

@Composable
fun OfflineBanner(text: String = "Sin conexión: mostrando datos guardados en el teléfono") {
    Surface(color = MaterialTheme.colorScheme.tertiaryContainer, modifier = Modifier.fillMaxWidth()) {
        Row(Modifier.padding(horizontal = 16.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.CloudOff, contentDescription = null, modifier = Modifier.padding(end = 8.dp))
            Text(text, style = MaterialTheme.typography.bodySmall)
        }
    }
}

/** Estado con color + texto (nunca sólo color). */
@Composable
fun StatusChip(status: String, label: String) {
    val color = when (status) {
        "active", "issued", "paid", "synced" -> PaidGreen
        "pending", "reprinted", "suspended" -> PendingAmber
        "cancelled", "rejected" -> MaterialTheme.colorScheme.error
        else -> MaterialTheme.colorScheme.outline
    }
    Text(
        label,
        color = color,
        fontWeight = FontWeight.SemiBold,
        style = MaterialTheme.typography.labelMedium,
        modifier = Modifier
            .background(color.copy(alpha = 0.12f), RoundedCornerShape(50))
            .padding(horizontal = 10.dp, vertical = 3.dp),
    )
}

@Composable
fun InfoRow(label: String, value: String?) {
    Row(Modifier.fillMaxWidth().padding(vertical = 3.dp)) {
        Text(label, color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.weight(0.42f))
        Text(value?.takeIf { it.isNotBlank() } ?: "—", modifier = Modifier.weight(0.58f))
    }
}

@Composable
fun SectionCard(title: String? = null, modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    Card(modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp)) {
            if (title != null) {
                Text(title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(bottom = 8.dp))
            }
            content()
        }
    }
}

/**
 * Vista previa del ticket térmico: papel blanco, el escudo del encabezado y
 * tipografía monoespaciada con los mismos renglones que se envían a la
 * impresora. Ocupa todo el ancho y ajusta la letra para que los renglones
 * (32 o 48 columnas) lleguen de orilla a orilla, como en el papel.
 */
@Composable
fun TicketPreview(lines: List<TicketLine>, modifier: Modifier = Modifier) {
    val columns = lines.maxOfOrNull { it.text.length }?.coerceAtLeast(24) ?: 32

    Surface(
        color = Color.White,
        shadowElevation = 2.dp,
        shape = RoundedCornerShape(4.dp),
        modifier = modifier.fillMaxWidth(),
    ) {
        BoxWithConstraints(Modifier.padding(horizontal = 8.dp, vertical = 12.dp)) {
            // Se mide el renglón más ancho a 100 sp (negritas, el caso más ancho) y se escala al ancho disponible.
            val measurer = rememberTextMeasurer()
            val widthPx = constraints.maxWidth.toFloat()
            val fontSize = remember(columns, widthPx) {
                val reference = measurer.measure(
                    "0".repeat(columns),
                    TextStyle(fontFamily = FontFamily.Monospace, fontWeight = FontWeight.Bold, fontSize = 100.sp, letterSpacing = 0.sp),
                    softWrap = false,
                ).size.width
                (100f * widthPx / reference * 0.98f).sp
            }

            Column(Modifier.fillMaxWidth()) {
                Image(
                    painter = painterResource(R.drawable.ticket_logo),
                    contentDescription = "Escudo del Ayuntamiento de Guadalupe",
                    modifier = Modifier
                        .align(Alignment.CenterHorizontally)
                        .height(72.dp)
                        .padding(bottom = 6.dp),
                )
                lines.forEach { line -> TicketText(line, fontSize) }
            }
        }
    }
}

@Composable
private fun TicketText(line: TicketLine, fontSize: TextUnit) {
    Text(
        text = line.text,
        color = Color.Black,
        fontFamily = FontFamily.Monospace,
        fontSize = fontSize,
        letterSpacing = 0.sp,
        // "double" = doble alto en la impresora (mismo ancho de columnas).
        lineHeight = if (line.size == "double") fontSize * 2.2f else fontSize * 1.3f,
        fontWeight = if (line.bold) FontWeight.Bold else FontWeight.Normal,
        textAlign = when (line.align) {
            "center" -> TextAlign.Center
            "right" -> TextAlign.End
            else -> TextAlign.Start
        },
        softWrap = false,
        modifier = Modifier.fillMaxWidth(),
    )
}
