package mx.gob.padron.cobro.data.local

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase

@Database(
    entities = [MerchantEntity::class, ConceptEntity::class, PendingChargeEntity::class],
    version = 2,
    exportSchema = true,
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun merchants(): MerchantDao
    abstract fun concepts(): ConceptDao
    abstract fun pendingCharges(): PendingChargeDao

    companion object {
        fun build(context: Context): AppDatabase =
            Room.databaseBuilder(context, AppDatabase::class.java, "cobro.db")
                .addMigrations(MIGRATION_1_2)
                .build()

        /** v2: varios tianguis por comerciante. Conserva la cola de cobros pendientes. */
        val MIGRATION_1_2 = object : Migration(1, 2) {
            override fun migrate(db: SupportSQLiteDatabase) {
                db.execSQL("ALTER TABLE merchants ADD COLUMN tianguisList TEXT NOT NULL DEFAULT ''")
            }
        }
    }
}
