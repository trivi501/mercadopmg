package mx.gob.padron.cobro

import android.app.Application

class CobroApp : Application() {
    lateinit var container: AppContainer
        private set

    override fun onCreate() {
        super.onCreate()
        container = AppContainer(this)

        if (container.session.isLoggedIn) {
            container.scheduler.schedulePeriodic()
        }
    }
}
