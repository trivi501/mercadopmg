package mx.gob.padron.cobro.ui.common

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
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
 * Vista previa del ticket térmico: papel blanco y tipografía monoespaciada,
 * con los mismos renglones que se envían a la impresora.
 */
@Composable
fun TicketPreview(lines: List<TicketLine>, modifier: Modifier = Modifier) {
    Surface(
        color = Color.White,
        shadowElevation = 2.dp,
        shape = RoundedCornerShape(4.dp),
        modifier = modifier.widthIn(max = 360.dp).fillMaxWidth(),
    ) {
        Column(Modifier.padding(12.dp)) {
            lines.forEach { line ->
                Text(
                    text = line.text,
                    color = Color.Black,
                    fontFamily = FontFamily.Monospace,
                    fontSize = if (line.size == "double") 13.sp else 11.sp,
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
        }
    }
}
