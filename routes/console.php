<?php

use Illuminate\Support\Facades\Schedule;

// Obligaciones anuales de uso de suelo al inicio de cada ejercicio fiscal.
Schedule::command('padron:anualidades')->yearlyOn(1, 1, '00:30');
