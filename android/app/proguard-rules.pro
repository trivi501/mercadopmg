# kotlinx.serialization
-keepattributes *Annotation*, InnerClasses
-dontnote kotlinx.serialization.**
-keepclassmembers class kotlinx.serialization.json.** { *** Companion; }
-keepclasseswithmembers class mx.gob.padron.cobro.** {
    kotlinx.serialization.KSerializer serializer(...);
}
-keep,includedescriptorclasses class mx.gob.padron.cobro.**$$serializer { *; }

# Retrofit
-keepattributes Signature, Exceptions
-keep,allowobfuscation,allowshrinking interface retrofit2.Call
-keep,allowobfuscation,allowshrinking class kotlin.coroutines.Continuation

# Tink (security-crypto) referencia anotaciones que sólo existen al compilar
-dontwarn com.google.errorprone.annotations.**
